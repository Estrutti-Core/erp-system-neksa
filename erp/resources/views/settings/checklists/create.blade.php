@extends('layouts.app')
@section('title', 'Novo Template de Checklist')

@section('content')
<div class="max-w-5xl" x-data="checklistBuilder({{ json_encode($blocks->map(fn($b) => [
    'id'   => $b->id,
    'name' => $b->name,
    'questions' => $b->questions->map(fn($q) => [
        'question_text'  => $q->question_text,
        'question_type'  => $q->question_type,
        'options_json'   => $q->options_json ?? [],
        'is_required'    => $q->is_required,
        'order'          => $q->order,
        'source_block_id'=> $b->id,
    ])->values(),
])->values()) }})">

    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('settings.checklists.index') }}" class="btn btn-secondary btn-sm">← Voltar</a>
    </div>

    <form action="{{ route('settings.checklists.store') }}" method="POST" @submit="prepareSubmit">
        @csrf

        {{-- ── Cabeçalho do Template ─────────────────────────────── --}}
        <div class="card mb-4">
            <div class="form-group mb-4">
                <label class="form-label">Nome do Checklist <span style="color:#dc2626">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name') }}" required placeholder="Ex: Instalação de Ar-Condicionado">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <textarea name="description" rows="2" class="form-control"
                    placeholder="Descreva quando este checklist deve ser usado...">{{ old('description') }}</textarea>
            </div>
        </div>

        {{-- ── Seções ────────────────────────────────────────────── --}}
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-bold text-lg" style="margin:0;">Seções e Perguntas</h3>
            <div class="flex gap-2">
                <button type="button" class="btn btn-secondary btn-sm" @click="addSection">
                    + Adicionar Seção
                </button>
            </div>
        </div>

        <div x-show="sections.length === 0" class="card text-center text-muted py-6" style="font-style:italic;">
            Nenhuma seção criada. Clique em "+ Adicionar Seção" para começar.
        </div>

        <template x-for="(section, si) in sections" :key="section._key">
            <div class="card mb-4" style="border-left: 3px solid #6366f1;">

                {{-- Cabeçalho da seção --}}
                <div class="flex gap-3 items-start mb-3">
                    <div style="flex:1;">
                        <label class="form-label" style="font-size:12px;">Título da Seção <span style="color:#dc2626">*</span></label>
                        <input type="text" :name="`sections[${si}][title]`" x-model="section.title"
                            class="form-control" placeholder="Ex: Verificação de Segurança" required>
                    </div>
                    <div style="flex:1;">
                        <label class="form-label" style="font-size:12px;">Descrição</label>
                        <input type="text" :name="`sections[${si}][order]`" x-model="section.order" type="hidden">
                        <input type="text" :name="`sections[${si}][description]`" x-model="section.description"
                            class="form-control" placeholder="Opcional">
                    </div>
                    <div style="padding-top:20px; display:flex; gap:6px;">
                        <button type="button" class="btn btn-secondary btn-sm" title="Mover para cima"
                            @click="moveSection(si, -1)" :disabled="si === 0" style="padding:6px 8px;">▲</button>
                        <button type="button" class="btn btn-secondary btn-sm" title="Mover para baixo"
                            @click="moveSection(si, 1)" :disabled="si === sections.length - 1" style="padding:6px 8px;">▼</button>
                        <button type="button" class="btn btn-secondary btn-sm" title="Remover seção"
                            @click="removeSection(si)" style="padding:6px 8px; color:#dc2626;">
                            <x-heroicon-o-trash class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                {{-- Perguntas da seção --}}
                <div class="border-t pt-3">
                    <template x-for="(q, qi) in section.questions" :key="q._key">
                        <div class="question-card mb-2">
                            <div class="question-header">
                                <div style="flex:1; min-width:200px;">
                                    <label class="form-label" style="font-size:11px;">Texto <span style="color:#dc2626">*</span></label>
                                    <input type="text" :name="`sections[${si}][questions][${qi}][question_text]`"
                                        x-model="q.question_text" class="form-control" required
                                        placeholder="Ex: EPI utilizado corretamente?">
                                </div>
                                <div style="width:180px;">
                                    <label class="form-label" style="font-size:11px;">Tipo <span style="color:#dc2626">*</span></label>
                                    <select :name="`sections[${si}][questions][${qi}][question_type]`"
                                        x-model="q.question_type" class="form-control" required>
                                        <option value="text">Texto Livre</option>
                                        <option value="checkbox">Checkbox (Sim/Não)</option>
                                        <option value="select">Seleção de Opções</option>
                                        <option value="photo">Foto</option>
                                        <option value="drawing">Desenho</option>
                                        <option value="signature">Assinatura Digital</option>
                                        <option value="label">Rótulo Informativo</option>
                                    </select>
                                </div>
                                <div style="width:90px; text-align:center;">
                                    <label class="form-label" style="font-size:11px;">Obrigatório</label>
                                    <div style="padding-top:6px;">
                                        <input type="hidden" :name="`sections[${si}][questions][${qi}][is_required]`" value="0">
                                        <input type="checkbox" :name="`sections[${si}][questions][${qi}][is_required]`"
                                            value="1" x-model="q.is_required" style="width:18px;height:18px;cursor:pointer;">
                                    </div>
                                </div>
                                <input type="hidden" :name="`sections[${si}][questions][${qi}][order]`" :value="qi * 10">
                                <template x-if="q.source_block_id">
                                    <input type="hidden" :name="`sections[${si}][questions][${qi}][source_block_id]`" :value="q.source_block_id">
                                </template>
                                <div style="padding-top:20px; display:flex; gap:4px;">
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        @click="moveQuestion(section, qi, -1)" :disabled="qi === 0"
                                        style="padding:4px 6px;" title="Subir">▲</button>
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        @click="moveQuestion(section, qi, 1)" :disabled="qi === section.questions.length - 1"
                                        style="padding:4px 6px;" title="Descer">▼</button>
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        @click="removeQuestion(section, qi)"
                                        style="padding:4px 6px;color:#dc2626;" title="Remover">
                                        <x-heroicon-o-x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            {{-- Campo de opções para tipo "select" --}}
                            <div x-show="q.question_type === 'select'" class="options-container mt-2">
                                <label class="form-label" style="font-size:11px;">
                                    Opções <span style="color:#dc2626">*</span>
                                    <span style="font-weight:400;color:#64748b;"> — uma por linha</span>
                                </label>
                                <textarea :name="`sections[${si}][questions][${qi}][options_text]`"
                                    x-model="q.options_text" rows="3" class="form-control"
                                    placeholder="Bom&#10;Regular&#10;Ruim"
                                    style="font-family:monospace;font-size:12px;"></textarea>
                            </div>

                            {{-- Badge de bloco reutilizável --}}
                            <template x-if="q.source_block_id">
                                <div style="margin-top:6px;">
                                    <span class="badge" style="background:#ede9fe;color:#6d28d9;font-size:10px;">
                                        Importado de bloco
                                    </span>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Ações de perguntas --}}
                    <div class="flex gap-2 mt-2">
                        <button type="button" class="btn btn-secondary btn-sm" @click="addQuestion(section)">
                            + Pergunta
                        </button>
                        <div class="relative" x-data="{ open: false }" x-show="{{ json_encode($blocks->count() > 0) }}">
                            <button type="button" class="btn btn-secondary btn-sm" @click="open = !open">
                                📦 Importar Bloco ▾
                            </button>
                            <div x-show="open" @click.outside="open = false"
                                style="position:absolute;top:100%;left:0;z-index:50;background:#fff;border:1px solid #e2e8f0;border-radius:8px;min-width:220px;box-shadow:0 4px 12px rgba(0,0,0,.1);">
                                <template x-for="block in blocks" :key="block.id">
                                    <button type="button"
                                        @click="importBlock(section, block); open = false"
                                        style="display:block;width:100%;text-align:left;padding:10px 14px;border:none;background:none;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:13px;"
                                        x-text="block.name">
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- ── Rodapé ────────────────────────────────────────────── --}}
        <div class="flex gap-2 justify-end mt-4">
            <a href="{{ route('settings.checklists.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Salvar Template</button>
        </div>
    </form>
</div>

<style>
.question-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
}
.question-header {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    flex-wrap: wrap;
}
.options-container {
    padding-top: 8px;
    border-top: 1px dashed #e2e8f0;
}
</style>

<script>
function checklistBuilder(blocks) {
    return {
        blocks: blocks,
        sections: [],
        _sectionKey: 0,
        _questionKey: 0,

        init() {
            // Inicia com uma seção padrão vazia
            this.addSection();
        },

        addSection() {
            this.sections.push({
                _key: this._sectionKey++,
                title: '',
                description: '',
                order: this.sections.length * 10,
                questions: [],
            });
        },

        removeSection(si) {
            if (!confirm('Remover esta seção e todas as suas perguntas?')) return;
            this.sections.splice(si, 1);
        },

        moveSection(si, dir) {
            const target = si + dir;
            if (target < 0 || target >= this.sections.length) return;
            [this.sections[si], this.sections[target]] = [this.sections[target], this.sections[si]];
            this.sections = [...this.sections];
        },

        addQuestion(section) {
            section.questions.push({
                _key: this._questionKey++,
                question_text: '',
                question_type: 'text',
                is_required: false,
                options_text: '',
                source_block_id: null,
            });
        },

        removeQuestion(section, qi) {
            section.questions.splice(qi, 1);
        },

        moveQuestion(section, qi, dir) {
            const target = qi + dir;
            if (target < 0 || target >= section.questions.length) return;
            [section.questions[qi], section.questions[target]] = [section.questions[target], section.questions[qi]];
            section.questions = [...section.questions];
        },

        importBlock(section, block) {
            block.questions.forEach(bq => {
                section.questions.push({
                    _key: this._questionKey++,
                    question_text: bq.question_text,
                    question_type: bq.question_type,
                    is_required: bq.is_required,
                    options_text: Array.isArray(bq.options_json) ? bq.options_json.join('\n') : '',
                    source_block_id: block.id,
                });
            });
        },

        prepareSubmit() {
            // Nada extra necessário — os inputs x-model já preenchem os campos hidden
        },
    };
}
</script>
@endsection
