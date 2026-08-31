<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChecklistBlock;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistSection;
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
        $blocks = ChecklistBlock::with('questions')->orderBy('name')->get();
        return view('settings.checklists.create', compact('blocks'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                                      => ['required', 'string', 'max:255'],
            'description'                               => ['nullable', 'string'],
            'sections'                                  => ['nullable', 'array'],
            'sections.*.title'                          => ['required', 'string', 'max:255'],
            'sections.*.description'                    => ['nullable', 'string'],
            'sections.*.order'                          => ['nullable', 'integer'],
            'sections.*.questions'                      => ['nullable', 'array'],
            'sections.*.questions.*.question_text'      => ['required', 'string', 'max:255'],
            'sections.*.questions.*.question_type'      => ['required', 'string', 'in:text,checkbox,select,photo,drawing,label,signature'],
            'sections.*.questions.*.is_required'        => ['nullable'],
            'sections.*.questions.*.order'              => ['nullable', 'integer'],
            'sections.*.questions.*.options_text'       => ['nullable', 'string'],
            'sections.*.questions.*.source_block_id'    => ['nullable', 'integer', 'exists:checklist_blocks,id'],
        ]);

        DB::transaction(function () use ($data) {
            $template = ChecklistTemplate::create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            foreach ($data['sections'] ?? [] as $sectionIndex => $sectionData) {
                $section = $template->sections()->create([
                    'title'       => $sectionData['title'],
                    'description' => $sectionData['description'] ?? null,
                    'order'       => $sectionData['order'] ?? $sectionIndex,
                ]);

                foreach ($sectionData['questions'] ?? [] as $qIndex => $qData) {
                    $section->questions()->create([
                        'checklist_template_id' => $template->id,
                        'question_text'         => $qData['question_text'],
                        'question_type'         => $qData['question_type'],
                        'options_json'          => $this->parseOptions($qData),
                        'is_required'           => $this->parseRequired($qData),
                        'order'                 => $qData['order'] ?? $qIndex,
                        'source_block_id'       => $qData['source_block_id'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('settings.checklists.index')
            ->with('success', 'Template de checklist criado com sucesso.');
    }

    public function edit(ChecklistTemplate $checklist)
    {
        $checklist->load(['sections.questions', 'questions' => function ($q) {
            $q->whereNull('checklist_section_id')->orderBy('order');
        }]);

        $blocks = ChecklistBlock::with('questions')->orderBy('name')->get();

        return view('settings.checklists.edit', compact('checklist', 'blocks'));
    }

    public function update(Request $request, ChecklistTemplate $checklist)
    {
        $data = $request->validate([
            'name'                                      => ['required', 'string', 'max:255'],
            'description'                               => ['nullable', 'string'],
            'sections'                                  => ['nullable', 'array'],
            'sections.*.id'                             => ['nullable', 'integer'],
            'sections.*.title'                          => ['required', 'string', 'max:255'],
            'sections.*.description'                    => ['nullable', 'string'],
            'sections.*.order'                          => ['nullable', 'integer'],
            'sections.*.questions'                      => ['nullable', 'array'],
            'sections.*.questions.*.id'                 => ['nullable', 'integer'],
            'sections.*.questions.*.question_text'      => ['required', 'string', 'max:255'],
            'sections.*.questions.*.question_type'      => ['required', 'string', 'in:text,checkbox,select,photo,drawing,label,signature'],
            'sections.*.questions.*.is_required'        => ['nullable'],
            'sections.*.questions.*.order'              => ['nullable', 'integer'],
            'sections.*.questions.*.options_text'       => ['nullable', 'string'],
            'sections.*.questions.*.source_block_id'    => ['nullable', 'integer', 'exists:checklist_blocks,id'],
        ]);

        DB::transaction(function () use ($data, $checklist) {
            $checklist->update([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $keptSectionIds   = [];
            $keptQuestionIds  = [];

            foreach ($data['sections'] ?? [] as $sectionIndex => $sectionData) {
                if (!empty($sectionData['id'])) {
                    $section = ChecklistSection::findOrFail($sectionData['id']);
                    $section->update([
                        'title'       => $sectionData['title'],
                        'description' => $sectionData['description'] ?? null,
                        'order'       => $sectionData['order'] ?? $sectionIndex,
                    ]);
                } else {
                    $section = $checklist->sections()->create([
                        'title'       => $sectionData['title'],
                        'description' => $sectionData['description'] ?? null,
                        'order'       => $sectionData['order'] ?? $sectionIndex,
                    ]);
                }

                $keptSectionIds[] = $section->id;

                foreach ($sectionData['questions'] ?? [] as $qIndex => $qData) {
                    $fields = [
                        'checklist_template_id' => $checklist->id,
                        'checklist_section_id'  => $section->id,
                        'question_text'         => $qData['question_text'],
                        'question_type'         => $qData['question_type'],
                        'options_json'          => $this->parseOptions($qData),
                        'is_required'           => $this->parseRequired($qData),
                        'order'                 => $qData['order'] ?? $qIndex,
                        'source_block_id'       => $qData['source_block_id'] ?? null,
                    ];

                    if (!empty($qData['id'])) {
                        $question = ChecklistQuestion::findOrFail($qData['id']);
                        $question->update($fields);
                    } else {
                        $question = ChecklistQuestion::create($fields);
                    }

                    $keptQuestionIds[] = $question->id;
                }
            }

            // Soft-delete seções removidas
            $checklist->sections()->whereNotIn('id', $keptSectionIds)->delete();

            // Soft-delete perguntas removidas
            $checklist->questions()->whereNotIn('id', $keptQuestionIds)->delete();
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

    // ─── Blocos Reutilizáveis ─────────────────────────────────────────────────

    /**
     * Retorna as perguntas de um bloco como JSON (para o builder importar inline).
     */
    public function blockQuestions(ChecklistBlock $block)
    {
        return response()->json([
            'block' => [
                'id'          => $block->id,
                'name'        => $block->name,
                'description' => $block->description,
                'questions'   => $block->questions->map(fn ($q) => [
                    'question_text'  => $q->question_text,
                    'question_type'  => $q->question_type,
                    'options_json'   => $q->options_json,
                    'is_required'    => $q->is_required,
                    'order'          => $q->order,
                    'source_block_id'=> $block->id,
                ]),
            ],
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

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
