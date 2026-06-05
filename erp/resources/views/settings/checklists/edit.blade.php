@extends('layouts.app')
@section('title', 'Editar Template de Checklist')

@section('content')
<div class="max-w-4xl">
    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('settings.checklists.index') }}" class="btn btn-secondary btn-sm">← Voltar</a>
    </div>

    <form action="{{ route('settings.checklists.update', $checklist) }}" method="POST" class="card">
        @csrf
        @method('PUT')

        <div class="form-group mb-4">
            <label for="name" class="form-label">Nome do Checklist <span style="color: #dc2626;">*</span></label>
            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $checklist->name) }}" required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mb-4">
            <label for="description" class="form-label">Descrição / Observações</label>
            <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $checklist->description) }}</textarea>
            @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="border-t my-4 pt-4">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-bold text-lg" style="margin: 0;">Perguntas / Itens de Verificação</h3>
                <button type="button" class="btn btn-secondary btn-sm" id="btn-add-question">+ Adicionar Pergunta</button>
            </div>

            <div class="table-wrap">
                <table class="w-full" id="questions-table">
                    <thead>
                        <tr>
                            <th>Texto da Pergunta <span style="color: #dc2626;">*</span></th>
                            <th style="width: 200px;">Tipo de Resposta <span style="color: #dc2626;">*</span></th>
                            <th style="width: 100px; text-align: center;">Obrigatório?</th>
                            <th style="width: 100px;">Ordem</th>
                            <th style="width: 80px; text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="questions-container">
                        @foreach($checklist->questions as $index => $q)
                        <tr class="question-row">
                            <td>
                                <input type="hidden" name="questions[{{ $index }}][id]" value="{{ $q->id }}">
                                <input type="text" name="questions[{{ $index }}][question_text]" class="form-control" value="{{ $q->question_text }}" required>
                            </td>
                            <td>
                                <select name="questions[{{ $index }}][question_type]" class="form-control" required>
                                    <option value="text" {{ $q->question_type === 'text' ? 'selected' : '' }}>Texto Livre</option>
                                    <option value="yes_no" {{ $q->question_type === 'yes_no' ? 'selected' : '' }}>Sim / Não</option>
                                    <option value="number" {{ $q->question_type === 'number' ? 'selected' : '' }}>Numérico</option>
                                    <option value="photo" {{ $q->question_type === 'photo' ? 'selected' : '' }}>Foto</option>
                                </select>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="hidden" name="questions[{{ $index }}][is_required]" value="0">
                                <input type="checkbox" name="questions[{{ $index }}][is_required]" value="1" {{ $q->is_required ? 'checked' : '' }} style="width: 18px; height: 18px; cursor: pointer;">
                            </td>
                            <td>
                                <input type="number" name="questions[{{ $index }}][order]" class="form-control" value="{{ $q->order }}" min="0">
                            </td>
                            <td style="text-align: right; vertical-align: middle;">
                                <button type="button" class="btn btn-secondary btn-sm btn-remove-question" style="color: #dc2626; padding: 4px 8px;">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div id="no-questions-msg" class="text-center text-muted py-4" style="font-style: italic; display: {{ $checklist->questions->isEmpty() ? 'block' : 'none' }};">
                Nenhuma pergunta adicionada. Clique em "+ Adicionar Pergunta" para começar.
            </div>
        </div>

        <div class="border-t mt-4 pt-4 flex gap-2 justify-end">
            <a href="{{ route('settings.checklists.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>
    </form>
</div>

<template id="question-row-template">
    <tr class="question-row">
        <td>
            <input type="text" name="questions[{index}][question_text]" class="form-control" placeholder="Ex: O aparelho liga normalmente?" required>
        </td>
        <td>
            <select name="questions[{index}][question_type]" class="form-control" required>
                <option value="text">Texto Livre</option>
                <option value="yes_no">Sim / Não</option>
                <option value="number">Numérico</option>
                <option value="photo">Foto</option>
            </select>
        </td>
        <td style="text-align: center; vertical-align: middle;">
            <input type="hidden" name="questions[{index}][is_required]" value="0">
            <input type="checkbox" name="questions[{index}][is_required]" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
        </td>
        <td>
            <input type="number" name="questions[{index}][order]" class="form-control" value="{orderValue}" min="0">
        </td>
        <td style="text-align: right; vertical-align: middle;">
            <button type="button" class="btn btn-secondary btn-sm btn-remove-question" style="color: #dc2626; padding: 4px 8px;">
                <x-heroicon-o-trash class="w-4 h-4" />
            </button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('questions-container');
    const template = document.getElementById('question-row-template').innerHTML;
    const btnAdd = document.getElementById('btn-add-question');
    const msgNoQuestions = document.getElementById('no-questions-msg');
    let questionIndex = {{ $checklist->questions->count() }};

    function toggleMsg() {
        if (container.children.length === 0) {
            msgNoQuestions.style.display = 'block';
        } else {
            msgNoQuestions.style.display = 'none';
        }
    }

    btnAdd.addEventListener('click', function () {
        const orderVal = container.children.length * 10;
        const html = template
            .replace(/{index}/g, questionIndex)
            .replace(/{orderValue}/g, orderVal);

        container.insertAdjacentHTML('beforeend', html);
        questionIndex++;
        toggleMsg();
    });

    container.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-remove-question');
        if (btn) {
            const row = btn.closest('.question-row');
            row.remove();
            toggleMsg();
        }
    });
});
</script>
@endsection
