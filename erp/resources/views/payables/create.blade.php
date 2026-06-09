@extends('layouts.app')

@section('title', 'Novo Contas a Pagar')

@section('content')
<div class="card max-w-4xl mx-auto">
    <div class="card-body">
        <form method="POST" action="{{ route('payables.store') }}" id="payableForm">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="supplier_id" class="form-label">Fornecedor (opcional)</label>
                    <select name="supplier_id" id="supplier_id" class="form-control">
                        <option value="">Fornecedor Avulso / Não Identificado</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                {{ $supplier->name }} ({{ $supplier->document }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="competence_date" class="form-label">Data de Competência</label>
                    <input type="date" name="competence_date" id="competence_date" class="form-control" value="{{ old('competence_date', today()->toDateString()) }}" required>
                </div>
            </div>

            <div class="mb-6">
                <label for="description" class="form-label">Descrição / Histórico</label>
                <input type="text" name="description" id="description" class="form-control" value="{{ old('description') }}" placeholder="Ex: Compra de ferramentas e insumos para OS" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="total_amount" class="form-label">Valor Total (R$)</label>
                    <input type="number" step="0.01" name="total_amount" id="total_amount" class="form-control text-lg font-semibold" value="{{ old('total_amount') }}" placeholder="0,00" required>
                </div>
                <div>
                    <label for="notes" class="form-label">Observações</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Observações adicionais do contas a pagar...">{{ old('notes') }}</textarea>
                </div>
            </div>

            <hr class="my-6 border-gray-200">

            <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Parcelas</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addInstallmentRow()">
                        <x-heroicon-o-plus class="w-4 h-4"/> Adicionar Parcela
                    </button>
                </div>

                <div id="installmentsWrapper" class="space-y-3">
                    <!-- Gerado dinamicamente ou populado com erros de validação -->
                </div>

                <div class="mt-4 p-4 bg-gray-50 rounded flex justify-between items-center">
                    <span class="text-sm text-gray-600">Soma das parcelas:</span>
                    <span class="text-lg font-bold" id="installmentsSumText">R$ 0,00</span>
                </div>
                <div class="text-red-600 text-sm mt-2 hidden" id="amountMismatchError">
                    A soma das parcelas deve ser exatamente igual ao valor total informado.
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-8">
                <a href="{{ route('payables.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary px-6" id="submitBtn">Salvar Contas a Pagar</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    let installmentIndex = 0;

    function addInstallmentRow(dueDate = '', amount = '') {
        const wrapper = document.getElementById('installmentsWrapper');
        const index = installmentIndex++;
        
        const row = document.createElement('div');
        row.className = 'flex items-center gap-3 installment-row bg-white p-3 border border-gray-200 rounded';
        row.id = `installment-row-${index}`;
        
        row.innerHTML = `
            <div class="flex-1">
                <label class="form-label text-xs">Vencimento</label>
                <input type="date" name="installments[${index}][due_date]" class="form-control form-control-sm inst-date" value="${dueDate}" required>
            </div>
            <div style="width: 200px">
                <label class="form-label text-xs">Valor (R$)</label>
                <input type="number" step="0.01" name="installments[${index}][amount]" class="form-control form-control-sm inst-amount" value="${amount}" placeholder="0,00" required oninput="calculateSum()">
            </div>
            <div style="padding-top: 18px">
                <button type="button" class="btn btn-secondary btn-sm text-red-600 border-red-200 hover:bg-red-50" onclick="removeInstallmentRow(${index})">
                    <x-heroicon-o-trash class="w-4 h-4"/>
                </button>
            </div>
        `;
        
        wrapper.appendChild(row);
        calculateSum();
    }

    function removeInstallmentRow(index) {
        const row = document.getElementById(`installment-row-${index}`);
        if (row) {
            row.remove();
        }
        calculateSum();
    }

    function calculateSum() {
        const amounts = document.querySelectorAll('.inst-amount');
        let sum = 0;
        amounts.forEach(input => {
            const val = parseFloat(input.value);
            if (!isNaN(val)) {
                sum += val;
            }
        });
        
        document.getElementById('installmentsSumText').innerText = 'R$ ' + sum.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        
        const totalAmount = parseFloat(document.getElementById('total_amount').value);
        const errorDiv = document.getElementById('amountMismatchError');
        const submitBtn = document.getElementById('submitBtn');
        
        if (!isNaN(totalAmount) && Math.abs(sum - totalAmount) > 0.01) {
            errorDiv.classList.remove('hidden');
            submitBtn.disabled = true;
        } else {
            errorDiv.classList.add('hidden');
            submitBtn.disabled = false;
        }
    }

    document.getElementById('total_amount').addEventListener('input', calculateSum);

    document.addEventListener('DOMContentLoaded', () => {
        const totalInput = document.getElementById('total_amount');
        totalInput.addEventListener('change', () => {
            if (document.querySelectorAll('.installment-row').length === 0) {
                const today = new Date().toISOString().split('T')[0];
                addInstallmentRow(today, totalInput.value);
            }
        });
        
        @if(old('installments'))
            @foreach(old('installments') as $inst)
                addInstallmentRow('{{ $inst['due_date'] }}', '{{ $inst['amount'] }}');
            @endforeach
        @else
            addInstallmentRow(new Date().toISOString().split('T')[0], '');
        @endif
    });
</script>
@endpush
@endsection
