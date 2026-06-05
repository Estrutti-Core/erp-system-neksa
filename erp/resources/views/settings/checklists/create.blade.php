@extends('layouts.app')
@section('title', 'Novo Template de Checklist')

@section('content')
<div class="max-w-4xl">
    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('settings.checklists.index') }}" class="btn btn-secondary btn-sm">← Voltar</a>
    </div>

    <form action="{{ route('settings.checklists.store') }}" method="POST" class="card">
        @csrf

        <div class="form-group mb-4">
            <label for="name" class="form-label">Nome do Checklist <span style="color: #dc2626;">*</span></label>
            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mb-4">
            <label for="description" class="form-label">Descrição / Observações</label>
            <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
        </div>

        <div class="border-t my-4 pt-4">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-bold text-lg" style="margin: 0;">Perguntas / Itens de Verificação</h3>
                <button type="button" class="btn btn-secondary btn-sm" id="btn-add-question">+ Adicionar Pergunta</button>
            </div>

            <div id="questions-container"></div>

            <div id="no-questions-msg" class="text-center text-muted py-4" style="font-style: italic;">
                Nenhuma pergunta adicionada. Clique em "+ Adicionar Pergunta" para começar.
            </div>
        </div>

        <div class="border-t mt-4 pt-4 flex gap-2 justify-end">
            <a href="{{ route('settings.checklists.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Salvar Template</button>
        </div>
    </form>
</div>

<style>
.question-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
    position: relative;
}
.question-card .question-header {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    flex-wrap: wrap;
}
.question-type-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
    background: #ede9fe;
    color: #6d28d9;
    margin-top: 2px;
}
.options-container {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #e2e8f0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('questions-container');
    const btnAdd = document.getElementById('btn-add-question');
    const msgNoQuestions = document.getElementById('no-questions-msg');
    let questionIndex = 0;

    const questionTypes = {
        text:     'Texto Livre',
        checkbox: 'Checkbox (Sim/Não)',
        select:   'Seleção de Opções',
        photo:    'Foto',
        drawing:  'Desenho',
        label:    'Rótulo Informativo',
    };

    function toggleMsg() {
        msgNoQuestions.style.display = container.children.length === 0 ? 'block' : 'none';
    }

    function buildQuestionCard(index, data = {}) {
        const type = data.type || 'text';
        const isRequired = data.is_required !== false;
        const order = data.order ?? (container.children.length * 10);
        const hasOptions = data.options || [];

        const card = document.createElement('div');
        card.className = 'question-card';
        card.dataset.index = index;

        card.innerHTML = `
            <div class="question-header">
                <div style="flex:1; min-width:220px;">
                    <label class="form-label" style="font-size:12px;">Texto da Pergunta <span style="color:#dc2626">*</span></label>
                    <input type="text" name="questions[${index}][question_text]"
                        class="form-control" placeholder="Ex: O equipamento liga normalmente?"
                        value="${data.text || ''}" required>
                </div>
                <div style="width:200px;">
                    <label class="form-label" style="font-size:12px;">Tipo de Resposta <span style="color:#dc2626">*</span></label>
                    <select name="questions[${index}][question_type]" class="form-control question-type-select" required>
                        ${Object.entries(questionTypes).map(([val, label]) =>
                            `<option value="${val}" ${type === val ? 'selected' : ''}>${label}</option>`
                        ).join('')}
                    </select>
                </div>
                <div style="width:110px; text-align:center;">
                    <label class="form-label" style="font-size:12px;">Obrigatório?</label>
                    <div style="padding-top:6px;">
                        <input type="hidden" name="questions[${index}][is_required]" value="0">
                        <input type="checkbox" name="questions[${index}][is_required]" value="1"
                            ${isRequired ? 'checked' : ''} style="width:18px;height:18px;cursor:pointer;">
                    </div>
                </div>
                <div style="width:80px;">
                    <label class="form-label" style="font-size:12px;">Ordem</label>
                    <input type="number" name="questions[${index}][order]" class="form-control" value="${order}" min="0">
                </div>
                <div style="padding-top:20px;">
                    <button type="button" class="btn btn-secondary btn-sm btn-remove-question"
                        style="color:#dc2626;padding:6px 8px;" title="Remover pergunta">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                    </button>
                </div>
            </div>
            <div class="options-container select-options" style="display:${type === 'select' ? 'block' : 'none'}">
                <label class="form-label" style="font-size:12px;margin-bottom:6px;">
                    Opções do Select <span style="color:#dc2626">*</span>
                    <span style="font-weight:400;color:#64748b;"> — uma por linha</span>
                </label>
                <textarea name="questions[${index}][options_text]" class="form-control" rows="3"
                    placeholder="Ex:&#10;Bom&#10;Regular&#10;Ruim"
                    style="font-family:monospace;font-size:13px;">${hasOptions.join('\n')}</textarea>
            </div>
        `;

        // Mostrar/ocultar campo de opções ao mudar o tipo
        card.querySelector('.question-type-select').addEventListener('change', function () {
            card.querySelector('.select-options').style.display = this.value === 'select' ? 'block' : 'none';
        });

        // Remover pergunta
        card.querySelector('.btn-remove-question').addEventListener('click', function () {
            card.remove();
            toggleMsg();
        });

        return card;
    }

    btnAdd.addEventListener('click', function () {
        const card = buildQuestionCard(questionIndex++);
        container.appendChild(card);
        toggleMsg();
    });

    // Primeira pergunta automática
    btnAdd.click();
});
</script>
@endsection
