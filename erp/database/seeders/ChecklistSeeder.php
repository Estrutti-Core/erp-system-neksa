<?php

namespace Database\Seeders;

use App\Models\ChecklistBlock;
use App\Models\ChecklistBlockQuestion;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSection;
use App\Models\ChecklistTemplate;
use Illuminate\Database\Seeder;

class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        // ── Blocos Reutilizáveis (biblioteca de perguntas) ─────────────────

        $blockEpi = ChecklistBlock::updateOrCreate(
            ['name' => 'Segurança e EPI'],
            ['description' => 'Verificações padrão de segurança e equipamentos de proteção individual']
        );

        $epiQuestions = [
            ['question_text' => 'EPI adequado em uso (capacete, luvas, óculos, sapato de segurança)?', 'question_type' => 'checkbox', 'is_required' => true,  'order' => 10],
            ['question_text' => 'Área de trabalho sinalizada e isolada corretamente?',                 'question_type' => 'checkbox', 'is_required' => true,  'order' => 20],
            ['question_text' => 'Ferramentas inspecionadas e em bom estado?',                          'question_type' => 'checkbox', 'is_required' => true,  'order' => 30],
            ['question_text' => 'Risco elétrico identificado e mitigado?',                             'question_type' => 'checkbox', 'is_required' => true,  'order' => 40],
            ['question_text' => 'Observações de segurança (se houver)',                                'question_type' => 'text',     'is_required' => false, 'order' => 50],
        ];

        foreach ($epiQuestions as $q) {
            ChecklistBlockQuestion::updateOrCreate(
                ['checklist_block_id' => $blockEpi->id, 'question_text' => $q['question_text']],
                array_merge($q, ['checklist_block_id' => $blockEpi->id])
            );
        }

        $blockFoto = ChecklistBlock::updateOrCreate(
            ['name' => 'Registro Fotográfico Padrão'],
            ['description' => 'Fotos obrigatórias de evidência antes e depois do serviço']
        );

        $fotoQuestions = [
            ['question_text' => 'Foto do ambiente antes do início do serviço',  'question_type' => 'photo', 'is_required' => true,  'order' => 10],
            ['question_text' => 'Foto do trabalho executado / instalação final', 'question_type' => 'photo', 'is_required' => true,  'order' => 20],
            ['question_text' => 'Foto do rack / painel organizado',              'question_type' => 'photo', 'is_required' => false, 'order' => 30],
        ];

        foreach ($fotoQuestions as $q) {
            ChecklistBlockQuestion::updateOrCreate(
                ['checklist_block_id' => $blockFoto->id, 'question_text' => $q['question_text']],
                array_merge($q, ['checklist_block_id' => $blockFoto->id])
            );
        }

        // ── Template 1: Instalação de Infraestrutura de Rede ──────────────

        $tInstRede = ChecklistTemplate::updateOrCreate(
            ['name' => 'Instalação de Infraestrutura de Rede'],
            ['description' => 'Checklist completo para instalações de cabeamento estruturado, switches e APs']
        );

        $this->deleteTemplateSections($tInstRede);

        $s1 = ChecklistSection::create(['checklist_template_id' => $tInstRede->id, 'title' => 'Pré-Trabalho',          'description' => 'Verificações antes de iniciar', 'order' => 10]);
        $s2 = ChecklistSection::create(['checklist_template_id' => $tInstRede->id, 'title' => 'Execução',              'description' => 'Etapas da instalação em campo',  'order' => 20]);
        $s3 = ChecklistSection::create(['checklist_template_id' => $tInstRede->id, 'title' => 'Testes e Finalização',  'description' => 'Validação e encerramento',       'order' => 30]);

        // Seção 1 — Pré-Trabalho (contém perguntas do bloco EPI)
        $this->createQuestions($tInstRede->id, $s1->id, [
            ['question_text' => 'Ordem de Serviço apresentada e confirmada com o responsável local?', 'question_type' => 'checkbox', 'is_required' => true,  'order' => 10],
            ['question_text' => 'Planta/layout do local disponível?',                                 'question_type' => 'checkbox', 'is_required' => false, 'order' => 20],
            // Perguntas do bloco EPI copiadas com source_block_id
            ['question_text' => 'EPI adequado em uso (capacete, luvas, óculos, sapato de segurança)?', 'question_type' => 'checkbox', 'is_required' => true,  'order' => 30, 'source_block_id' => $blockEpi->id],
            ['question_text' => 'Área de trabalho sinalizada e isolada corretamente?',                 'question_type' => 'checkbox', 'is_required' => true,  'order' => 40, 'source_block_id' => $blockEpi->id],
            ['question_text' => 'Risco elétrico identificado e mitigado?',                             'question_type' => 'checkbox', 'is_required' => true,  'order' => 50, 'source_block_id' => $blockEpi->id],
            ['question_text' => 'Foto do ambiente antes do início do serviço',                         'question_type' => 'photo',    'is_required' => true,  'order' => 60, 'source_block_id' => $blockFoto->id],
        ]);

        // Seção 2 — Execução
        $this->createQuestions($tInstRede->id, $s2->id, [
            ['question_text' => 'Tipo de cabeamento utilizado',      'question_type' => 'select', 'is_required' => true,  'order' => 10, 'options_json' => ['Cat5e', 'Cat6', 'Cat6A', 'Fibra Óptica', 'Outro']],
            ['question_text' => 'Quantidade de pontos instalados',   'question_type' => 'text',   'is_required' => true,  'order' => 20],
            ['question_text' => 'Todos os cabos identificados/etiquetados?', 'question_type' => 'checkbox', 'is_required' => true, 'order' => 30],
            ['question_text' => 'Patch panel organizado no rack?',   'question_type' => 'checkbox', 'is_required' => false, 'order' => 40],
            ['question_text' => 'Equipamentos ativos (switches/APs) instalados e energizados?', 'question_type' => 'checkbox', 'is_required' => true, 'order' => 50],
            ['question_text' => 'Foto do trabalho executado / instalação final', 'question_type' => 'photo', 'is_required' => true, 'order' => 60, 'source_block_id' => $blockFoto->id],
        ]);

        // Seção 3 — Testes e Finalização
        $this->createQuestions($tInstRede->id, $s3->id, [
            ['question_text' => 'Teste de conectividade realizado em todos os pontos?', 'question_type' => 'checkbox', 'is_required' => true,  'order' => 10],
            ['question_text' => 'Resultado dos testes de cabo (certificação/wiremap)',  'question_type' => 'select',   'is_required' => true,  'order' => 20, 'options_json' => ['Aprovado', 'Aprovado com ressalvas', 'Reprovado — retrabalho necessário']],
            ['question_text' => 'Velocidade de link confirmada nos switches?',          'question_type' => 'checkbox', 'is_required' => true,  'order' => 30],
            ['question_text' => 'Limpeza do local realizada após o serviço?',           'question_type' => 'checkbox', 'is_required' => true,  'order' => 40],
            ['question_text' => 'Observações gerais / pendências',                      'question_type' => 'text',     'is_required' => false, 'order' => 50],
            ['question_text' => 'Assinatura do responsável pelo recebimento',           'question_type' => 'signature','is_required' => true,  'order' => 60],
        ]);

        // ── Template 2: Manutenção Preventiva de Ativos ───────────────────

        $tManutPrev = ChecklistTemplate::updateOrCreate(
            ['name' => 'Manutenção Preventiva de Ativos de Rede'],
            ['description' => 'Verificação periódica de roteadores, switches, APs e cabeamento estruturado']
        );

        $this->deleteTemplateSections($tManutPrev);

        $m1 = ChecklistSection::create(['checklist_template_id' => $tManutPrev->id, 'title' => 'Diagnóstico Inicial', 'description' => 'Estado atual do ambiente', 'order' => 10]);
        $m2 = ChecklistSection::create(['checklist_template_id' => $tManutPrev->id, 'title' => 'Intervenção',          'description' => 'Ações realizadas',        'order' => 20]);
        $m3 = ChecklistSection::create(['checklist_template_id' => $tManutPrev->id, 'title' => 'Verificação Final',    'description' => 'Confirmação de melhoria', 'order' => 30]);

        $this->createQuestions($tManutPrev->id, $m1->id, [
            ['question_text' => 'Foto do rack/equipamento no estado inicial',       'question_type' => 'photo',    'is_required' => true,  'order' => 10, 'source_block_id' => $blockFoto->id],
            ['question_text' => 'Condição física dos equipamentos',                 'question_type' => 'select',   'is_required' => true,  'order' => 20, 'options_json' => ['Ótimo', 'Bom', 'Regular', 'Ruim — necessita substituição']],
            ['question_text' => 'Equipamentos com alarme/LED de falha?',            'question_type' => 'checkbox', 'is_required' => true,  'order' => 30],
            ['question_text' => 'Temperatura ambiente do rack (°C)',                'question_type' => 'text',     'is_required' => false, 'order' => 40],
            ['question_text' => 'Qual o equipamento com problema (se houver)?',     'question_type' => 'text',     'is_required' => false, 'order' => 50],
        ]);

        $this->createQuestions($tManutPrev->id, $m2->id, [
            ['question_text' => 'Limpeza de filtros e ventilação realizada?',       'question_type' => 'checkbox', 'is_required' => true,  'order' => 10],
            ['question_text' => 'Firmware atualizado?',                             'question_type' => 'checkbox', 'is_required' => false, 'order' => 20],
            ['question_text' => 'Versão do firmware instalado',                     'question_type' => 'text',     'is_required' => false, 'order' => 30],
            ['question_text' => 'Backup de configuração realizado antes das alterações?', 'question_type' => 'checkbox', 'is_required' => true, 'order' => 40],
            ['question_text' => 'Cabos soltos ou danificados reconectados/substituídos?', 'question_type' => 'checkbox', 'is_required' => true, 'order' => 50],
        ]);

        $this->createQuestions($tManutPrev->id, $m3->id, [
            ['question_text' => 'Todos os equipamentos operando normalmente após manutenção?', 'question_type' => 'checkbox', 'is_required' => true,  'order' => 10],
            ['question_text' => 'Resultado geral da manutenção',                               'question_type' => 'select',   'is_required' => true,  'order' => 20, 'options_json' => ['Concluída sem pendências', 'Concluída com ressalvas', 'Pendência — retorno necessário']],
            ['question_text' => 'Foto do rack/equipamento após a manutenção',                  'question_type' => 'photo',    'is_required' => true,  'order' => 30, 'source_block_id' => $blockFoto->id],
            ['question_text' => 'Observações e recomendações para o próximo ciclo',            'question_type' => 'text',     'is_required' => false, 'order' => 40],
            ['question_text' => 'Assinatura do responsável técnico',                           'question_type' => 'signature','is_required' => true,  'order' => 50],
        ]);

        // ── Template 3: Vistoria Rápida de Rack e Cabos ───────────────────

        $tVistoria = ChecklistTemplate::updateOrCreate(
            ['name' => 'Vistoria Rápida de Rack e Cabos'],
            ['description' => 'Inspeção simplificada para visitas técnicas rápidas e relatórios de campo']
        );

        $this->deleteTemplateSections($tVistoria);

        $v1 = ChecklistSection::create(['checklist_template_id' => $tVistoria->id, 'title' => 'Inspeção Física',  'description' => null, 'order' => 10]);
        $v2 = ChecklistSection::create(['checklist_template_id' => $tVistoria->id, 'title' => 'Documentação',    'description' => null, 'order' => 20]);

        $this->createQuestions($tVistoria->id, $v1->id, [
            ['question_text' => 'ATENÇÃO: Desligue equipamentos desnecessários antes da inspeção.', 'question_type' => 'label',    'is_required' => false, 'order' => 10],
            ['question_text' => 'Rack fixado corretamente na parede/piso?',         'question_type' => 'checkbox', 'is_required' => true,  'order' => 20],
            ['question_text' => 'Organização do cabeamento dentro do rack',         'question_type' => 'select',   'is_required' => true,  'order' => 30, 'options_json' => ['Organizado', 'Parcialmente organizado', 'Desorganizado — necessita intervenção']],
            ['question_text' => 'Identificação/etiquetagem dos cabos',              'question_type' => 'select',   'is_required' => true,  'order' => 40, 'options_json' => ['Completa', 'Parcial', 'Ausente']],
            ['question_text' => 'Existem cabos danificados visivelmente?',          'question_type' => 'checkbox', 'is_required' => true,  'order' => 50],
            ['question_text' => 'Foto geral do rack',                               'question_type' => 'photo',    'is_required' => true,  'order' => 60, 'source_block_id' => $blockFoto->id],
        ]);

        $this->createQuestions($tVistoria->id, $v2->id, [
            ['question_text' => 'Nível de prioridade para intervenção',             'question_type' => 'select',   'is_required' => true,  'order' => 10, 'options_json' => ['Baixo — próxima preventiva', 'Médio — agendar em até 30 dias', 'Alto — intervenção imediata']],
            ['question_text' => 'Descrição das irregularidades encontradas',        'question_type' => 'text',     'is_required' => false, 'order' => 20],
            ['question_text' => 'Assinatura do técnico responsável pela vistoria',  'question_type' => 'signature','is_required' => true,  'order' => 30],
        ]);

        $this->command->info('✓ ChecklistSeeder: 2 blocos + 3 templates criados com sucesso.');
    }

    private function deleteTemplateSections(ChecklistTemplate $template): void
    {
        // Limpa seções e perguntas existentes para re-seed idempotente
        $template->sections()->forceDelete();
        $template->allQuestions()->forceDelete();
    }

    private function createQuestions(int $templateId, int $sectionId, array $questions): void
    {
        foreach ($questions as $q) {
            ChecklistQuestion::create([
                'checklist_template_id' => $templateId,
                'checklist_section_id'  => $sectionId,
                'source_block_id'       => $q['source_block_id'] ?? null,
                'question_text'         => $q['question_text'],
                'question_type'         => $q['question_type'],
                'options_json'          => $q['options_json'] ?? null,
                'is_required'           => $q['is_required'],
                'order'                 => $q['order'],
            ]);
        }
    }
}
