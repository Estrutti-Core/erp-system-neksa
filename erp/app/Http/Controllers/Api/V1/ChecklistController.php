<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChecklistAnswer;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChecklistController extends Controller
{
    /**
     * GET /api/v1/service-orders/{serviceOrder}/checklists
     *
     * Retorna o payload offline-first completo: seções + perguntas + respostas já salvas.
     * O mobile armazena esse JSON localmente e não precisa de chamadas adicionais para renderizar.
     */
    public function index(ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorize('view', $serviceOrder);

        $serviceOrder->load([
            'checklists' => fn ($q) => $q->where('is_inactive', false),
            'checklists.instancedSections.questions.answer',
        ]);

        $payload = $serviceOrder->checklists->map(function (ServiceOrderChecklist $checklist) use ($serviceOrder) {
            return $this->buildChecklistPayload($checklist, $serviceOrder);
        });

        return response()->json([
            'data' => $payload,
        ]);
    }

    /**
     * PATCH /api/v1/checklist-instances/{checklist}/answers
     *
     * Sync parcial ou total de respostas. Pode ser chamado:
     *   - A cada resposta individual (online)
     *   - Em batch ao reconectar (offline → online)
     *
     * Body:
     * {
     *   "answers": [
     *     { "question_id": 201, "value": "true", "photo_base64": null, "answered_at_local": "..." },
     *     { "question_id": 203, "value": null, "photo_base64": "data:image/jpeg;base64,/9j/...", "answered_at_local": "..." }
     *   ],
     *   "mark_as_filled": false
     * }
     */
    public function syncAnswers(Request $request, ServiceOrderChecklist $checklist): JsonResponse
    {
        $this->authorize('update', $checklist->serviceOrder);

        abort_if($checklist->is_inactive, 403, 'Este checklist está inativo.');

        $validated = $request->validate([
            'answers'                       => ['required', 'array', 'min:1'],
            'answers.*.question_id'         => ['required', 'integer', 'exists:service_order_checklist_questions,id'],
            'answers.*.value'               => ['nullable', 'string', 'max:5000'],
            'answers.*.photo_base64'        => ['nullable', 'string'],
            'answers.*.answered_at_local'   => ['nullable', 'date'],
            'mark_as_filled'                => ['nullable', 'boolean'],
        ]);

        // Garante que as perguntas pertencem a este checklist (segurança)
        $validQuestionIds = $checklist->instancedQuestions()->pluck('id')->flip();

        DB::transaction(function () use ($validated, $checklist, $validQuestionIds) {
            foreach ($validated['answers'] as $answerData) {
                $questionId = $answerData['question_id'];

                if (!isset($validQuestionIds[$questionId])) {
                    continue;
                }

                $photoPath = null;

                if (!empty($answerData['photo_base64'])) {
                    $photoPath = $this->storePhotoFromBase64(
                        $answerData['photo_base64'],
                        $checklist->service_order_id,
                    );
                }

                $question = $checklist->instancedQuestions()->find($questionId);

                ChecklistAnswer::updateOrCreate(
                    [
                        'service_order_checklist_id'          => $checklist->id,
                        'service_order_checklist_question_id' => $questionId,
                    ],
                    array_filter([
                        'checklist_question_id' => $question->checklist_question_id,
                        'answer_value'          => $answerData['value'] ?? null,
                        'photo_path'            => $photoPath,
                    ], fn ($v) => $v !== null)
                );
            }

            if ($validated['mark_as_filled'] ?? false) {
                $checklist->update([
                    'filled_at' => now(),
                    'filled_by' => request()->user()->id,
                ]);
            }
        });

        // Retorna o checklist atualizado para o mobile sincronizar o estado local
        $checklist->load('instancedSections.questions.answer');

        return response()->json([
            'message'  => 'Respostas sincronizadas com sucesso.',
            'checklist' => $this->buildChecklistPayload($checklist, $checklist->serviceOrder),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function buildChecklistPayload(ServiceOrderChecklist $checklist, ServiceOrder $serviceOrder): array
    {
        $sections = $checklist->instancedSections->map(function ($section) {
            $questions = $section->questions->map(function ($question) {
                $answer = $question->answer;

                return [
                    'id'          => $question->id,
                    'type'        => $question->question_type,
                    'text'        => $question->question_text,
                    'is_required' => $question->is_required,
                    'order'       => $question->order,
                    'options'     => $question->options_json,
                    'answer'      => $answer ? [
                        'id'         => $answer->id,
                        'value'      => $answer->answer_value,
                        'photo_url'  => $answer->photo_url,
                    ] : null,
                ];
            });

            return [
                'id'          => $section->id,
                'title'       => $section->title,
                'description' => $section->description,
                'order'       => $section->order,
                'questions'   => $questions,
            ];
        });

        $allQuestions = $checklist->instancedSections->flatMap(fn ($s) => $s->questions);
        $required     = $allQuestions->where('is_required', true);
        $answered     = $allQuestions->filter(fn ($q) => $q->answer !== null);
        $reqAnswered  = $required->filter(fn ($q) => $q->answer !== null);
        $total        = $allQuestions->count();

        return [
            'id'               => $checklist->id,
            'service_order_id' => $serviceOrder->id,
            'template_name'    => $checklist->template?->name ?? 'Checklist',
            'is_inactive'      => $checklist->is_inactive,
            'filled_at'        => $checklist->filled_at?->toIso8601String(),
            'filled_by_name'   => $checklist->filledBy?->name,
            'progress'         => [
                'total'            => $total,
                'required'         => $required->count(),
                'answered'         => $answered->count(),
                'required_answered'=> $reqAnswered->count(),
                'pct'              => $total > 0 ? (int) round($answered->count() / $total * 100) : 0,
            ],
            'sections'         => $sections,
            '_offline_meta'    => [
                'schema_version'       => 2,
                'payload_generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function storePhotoFromBase64(string $base64, int $serviceOrderId): ?string
    {
        if (!str_starts_with($base64, 'data:image')) {
            return null;
        }

        $parts = explode(',', $base64, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $decoded = base64_decode($parts[1], true);
        if ($decoded === false) {
            return null;
        }

        $filename = 'checklists/' . $serviceOrderId . '/' . Str::uuid() . '.jpg';

        // Converte para JPG via GD se disponível
        $image = @imagecreatefromstring($decoded);
        if ($image) {
            ob_start();
            imagejpeg($image, null, 85);
            $jpg = ob_get_clean();
            imagedestroy($image);
            Storage::disk('public')->put($filename, $jpg);
        } else {
            Storage::disk('public')->put($filename, $decoded);
        }

        return $filename;
    }
}
