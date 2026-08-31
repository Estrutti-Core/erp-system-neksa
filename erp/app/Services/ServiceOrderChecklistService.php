<?php

namespace App\Services;

use App\Models\ChecklistTemplate;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderChecklist;
use App\Models\ServiceOrderChecklistSection;
use App\Models\ChecklistQuestion;
use Illuminate\Support\Facades\DB;

class ServiceOrderChecklistService
{
    /**
     * Sincroniza os checklists de uma Ordem de Serviço com base nos serviços associados.
     *
     * ADR-004: Template ≠ Instância.
     * Ao criar uma nova instância de checklist, as seções e perguntas do template são copiadas
     * (snapshot) para service_order_checklist_sections e service_order_checklist_questions,
     * garantindo imutabilidade histórica.
     *
     * Proteção de evidências: checklists já preenchidos nunca são deletados.
     * Caso o serviço seja removido, o checklist preenchido é marcado como is_inactive=true.
     */
    public function syncRequiredChecklists(ServiceOrder $serviceOrder): void
    {
        // 1. Obter IDs de serviços vinculados como itens da OS
        $serviceIds = $serviceOrder->items()
            ->where('type', 'service')
            ->whereNotNull('service_id')
            ->pluck('service_id')
            ->toArray();

        // 2. Localizar templates obrigatórios com base nos serviços
        $requiredTemplateIds = DB::table('service_type_checklists')
            ->whereIn('service_id', $serviceIds)
            ->pluck('checklist_template_id')
            ->unique()
            ->toArray();

        // 3. Templates já instanciados na OS
        $existingChecklists = $serviceOrder->checklists()->get()->keyBy('checklist_template_id');
        $existingTemplateIds = $existingChecklists->keys()->toArray();

        // 4. Adicionar instâncias para novos templates requeridos
        $templatesToAdd = array_diff($requiredTemplateIds, $existingTemplateIds);
        foreach ($templatesToAdd as $templateId) {
            $template = ChecklistTemplate::with(['sections.questions', 'questions' => function ($q) {
                $q->whereNull('checklist_section_id')->orderBy('order');
            }])->find($templateId);

            if (!$template) continue;

            $this->instantiateChecklist($serviceOrder, $template);
        }

        // 5. Tratar templates que não são mais necessários
        $templatesToReview = array_diff($existingTemplateIds, $requiredTemplateIds);
        if (!empty($templatesToReview)) {
            foreach ($templatesToReview as $templateId) {
                $checklist = $existingChecklists->get($templateId);
                if (!$checklist) continue;

                if ($checklist->filled_at !== null) {
                    // Checklist preenchido = evidência operacional → marcar como inativo
                    $checklist->update(['is_inactive' => true]);
                } else {
                    // Checklist vazio e não mais necessário → deletar com segurança
                    $checklist->delete();
                }
            }
        }

        // 6. Reativar instâncias inativas caso o serviço seja readicionado
        $inactiveToReactivate = $serviceOrder->checklists()
            ->where('is_inactive', true)
            ->whereIn('checklist_template_id', $requiredTemplateIds)
            ->get();

        foreach ($inactiveToReactivate as $checklist) {
            $checklist->update(['is_inactive' => false]);
        }
    }

    /**
     * Cria uma instância de checklist com snapshot das seções e perguntas do template.
     *
     * ADR-004: A partir deste momento, a OS não depende mais do template original.
     * Edições futuras no template não afetam esta instância.
     */
    private function instantiateChecklist(ServiceOrder $serviceOrder, ChecklistTemplate $template): ServiceOrderChecklist
    {
        $checklist = $serviceOrder->checklists()->create([
            'checklist_template_id' => $template->id,
        ]);

        $sections = $template->sections;

        if ($sections->isEmpty()) {
            // Template sem seções: cria uma seção "Geral" implícita para manter
            // a estrutura uniforme no payload e na UI.
            $snapshotSection = $this->snapshotSection($checklist, null, 'Geral', null, 0);

            $ungroupedQuestions = $template->questions()
                ->whereNull('checklist_section_id')
                ->orderBy('order')
                ->get();

            foreach ($ungroupedQuestions as $question) {
                $this->snapshotQuestion($checklist, $snapshotSection, $question);
            }

            return $checklist;
        }

        foreach ($sections as $section) {
            $snapshotSection = $this->snapshotSection(
                $checklist,
                $section->id,
                $section->title,
                $section->description,
                $section->order,
            );

            foreach ($section->questions as $question) {
                $this->snapshotQuestion($checklist, $snapshotSection, $question);
            }
        }

        return $checklist;
    }

    private function snapshotSection(
        ServiceOrderChecklist $checklist,
        ?int $originalSectionId,
        string $title,
        ?string $description,
        int $order
    ): ServiceOrderChecklistSection {
        return $checklist->instancedSections()->create([
            'checklist_section_id' => $originalSectionId,
            'title'                => $title,
            'description'          => $description,
            'order'                => $order,
        ]);
    }

    private function snapshotQuestion(
        ServiceOrderChecklist $checklist,
        ServiceOrderChecklistSection $section,
        ChecklistQuestion $question
    ): void {
        $checklist->instancedQuestions()->create([
            'service_order_checklist_section_id' => $section->id,
            'checklist_question_id'              => $question->id,
            'question_text'                      => $question->question_text,
            'question_type'                      => $question->question_type,
            'options_json'                       => $question->options_json,
            'is_required'                        => $question->is_required,
            'order'                              => $question->order,
        ]);
    }
}
