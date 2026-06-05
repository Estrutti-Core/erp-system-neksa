<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistTemplateController extends Controller
{
    public function index()
    {
        $templates = ChecklistTemplate::withCount('questions')->get();
        return view('settings.checklists.index', compact('templates'));
    }

    public function create()
    {
        return view('settings.checklists.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                        => ['required', 'string', 'max:255'],
            'description'                 => ['nullable', 'string'],
            'questions'                   => ['nullable', 'array'],
            'questions.*.question_text'   => ['required', 'string', 'max:255'],
            'questions.*.question_type'   => ['required', 'string', 'in:text,checkbox,select,photo,drawing,label'],
            'questions.*.is_required'     => ['nullable'],
            'questions.*.order'           => ['nullable', 'integer'],
            'questions.*.options_text'    => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $template = ChecklistTemplate::create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            foreach ($data['questions'] ?? [] as $qData) {
                $template->questions()->create([
                    'question_text' => $qData['question_text'],
                    'question_type' => $qData['question_type'],
                    'options_json'  => $this->parseOptions($qData),
                    'is_required'   => $this->parseRequired($qData),
                    'order'         => $qData['order'] ?? 0,
                ]);
            }
        });

        return redirect()->route('settings.checklists.index')
            ->with('success', 'Template de checklist criado com sucesso.');
    }

    public function edit(ChecklistTemplate $checklist)
    {
        $checklist->load('questions');
        return view('settings.checklists.edit', compact('checklist'));
    }

    public function update(Request $request, ChecklistTemplate $checklist)
    {
        $data = $request->validate([
            'name'                        => ['required', 'string', 'max:255'],
            'description'                 => ['nullable', 'string'],
            'questions'                   => ['nullable', 'array'],
            'questions.*.id'              => ['nullable', 'integer'],
            'questions.*.question_text'   => ['required', 'string', 'max:255'],
            'questions.*.question_type'   => ['required', 'string', 'in:text,checkbox,select,photo,drawing,label'],
            'questions.*.is_required'     => ['nullable'],
            'questions.*.order'           => ['nullable', 'integer'],
            'questions.*.options_text'    => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $checklist) {
            $checklist->update([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $keepIds = [];

            foreach ($data['questions'] ?? [] as $qData) {
                $fields = [
                    'question_text' => $qData['question_text'],
                    'question_type' => $qData['question_type'],
                    'options_json'  => $this->parseOptions($qData),
                    'is_required'   => $this->parseRequired($qData),
                    'order'         => $qData['order'] ?? 0,
                ];

                if (!empty($qData['id'])) {
                    $question = ChecklistQuestion::findOrFail($qData['id']);
                    $question->update($fields);
                    $keepIds[] = $question->id;
                } else {
                    $question = $checklist->questions()->create($fields);
                    $keepIds[] = $question->id;
                }
            }

            // Soft-delete perguntas removidas da lista
            $checklist->questions()->whereNotIn('id', $keepIds)->delete();
        });

        return redirect()->route('settings.checklists.index')
            ->with('success', 'Template de checklist atualizado com sucesso.');
    }

    public function destroy(ChecklistTemplate $checklist)
    {
        $checklist->delete();
        return redirect()->route('settings.checklists.index')
            ->with('success', 'Template de checklist excluído.');
    }

    /**
     * Converte o campo options_text (uma opção por linha) para array JSON.
     */
    private function parseOptions(array $qData): ?array
    {
        if (($qData['question_type'] ?? '') !== 'select') {
            return null;
        }

        $raw = trim($qData['options_text'] ?? '');
        if (empty($raw)) {
            return null;
        }

        return array_values(array_filter(
            array_map('trim', explode("\n", $raw))
        ));
    }

    private function parseRequired(array $qData): bool
    {
        return isset($qData['is_required']) && ($qData['is_required'] === '1' || $qData['is_required'] === true);
    }
}
