@extends('layouts.app')

@section('title', 'Novo Contas a Receber')

@section('content')
<div class="card max-w-4xl mx-auto">
    <div class="card-body">
        <form method="POST" action="{{ route('receivables.store') }}" id="receivableForm">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="form-group" style="position: relative;">
                    <label for="client_id" class="form-label">Cliente (opcional)</label>
                    
                    <!-- Container de Busca (Escondido após seleção) -->
                    <div id="client-search-container" style="position: relative;">
                        <input type="text" id="client-search-input" class="form-control" placeholder="Buscar cliente por nome ou CNPJ..." autocomplete="off">
                        <div id="client-autocomplete-results" style="display: none; position: absolute; left: 0; right: 0; top: 46px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; z-index: 100; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); max-height: 240px; overflow-y: auto;">
                        </div>
                    </div>

                    <!-- Card do Cliente Selecionado -->
                    <div id="client-details-card" style="display: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; position: relative;">
                        <div style="font-weight: 700; color: #0f172a; font-size: 14px;" id="client-card-name"></div>
                        <div style="color: #64748b; font-size: 12px; margin-top: 4px;" id="client-card-document"></div>
                        <button type="button" onclick="clearSelectedClient()" style="position: absolute; right: 12px; top: 12px; color: #ef4444; border: none; background: transparent; cursor: pointer; font-size: 12px; font-weight: 600;">Alterar</button>
                    </div>

                    <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id') }}">
                    @error('client_id')<div class="invalid-feedback" style="display:block;">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="competence_date" class="form-label">Data de Competência</label>
                    <input type="date" name="competence_date" id="competence_date" class="form-control" value="{{ old('competence_date', today()->toDateString()) }}" required>
                </div>
            </div>

            <div class="mb-6">
                <label for="description" class="form-label">Descrição / Histórico</label>
                <input type="text" name="description" id="description" class="form-control" value="{{ old('description') }}" placeholder="Ex: Prestação de serviços de consultoria" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="total_amount" class="form-label">Valor Total (R$)</label>
                    <input type="number" step="0.01" name="total_amount" id="total_amount" class="form-control text-lg font-semibold" value="{{ old('total_amount') }}" placeholder="0,00" required>
                </div>
                <div>
                    <label for="notes" class="form-label">Observações</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Observações adicionais do contas a receber...">{{ old('notes') }}</textarea>
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
                <a href="{{ route('receivables.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary px-6" id="submitBtn">Salvar Contas a Receber</button>
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

    // Adiciona primeira parcela padrão se estiver vazio
    document.addEventListener('DOMContentLoaded', () => {
        const totalInput = document.getElementById('total_amount');
        totalInput.addEventListener('change', () => {
            if (document.querySelectorAll('.installment-row').length === 0) {
                const today = new Date().toISOString().split('T')[0];
                addInstallmentRow(today, totalInput.value);
            }
        });
        
        // Se houver dados de old inputs (caso de erro na validação)
        @if(old('installments'))
            @foreach(old('installments') as $inst)
                addInstallmentRow('{{ $inst['due_date'] }}', '{{ $inst['amount'] }}');
            @endforeach
        @else
            // Cria uma parcela default de início
            addInstallmentRow(new Date().toISOString().split('T')[0], '');
        @endif

        // Client Autocomplete Logic
        const clientInput = document.getElementById('client-search-input');
        const clientResults = document.getElementById('client-autocomplete-results');
        const hiddenClientId = document.getElementById('client_id');
        const clientSearchContainer = document.getElementById('client-search-container');
        const clientDetailsCard = document.getElementById('client-details-card');
        const clientCardName = document.getElementById('client-card-name');
        const clientCardDocument = document.getElementById('client-card-document');

        clientInput.addEventListener('input', function() {
            const query = this.value;
            if (query.length < 2) {
                clientResults.style.display = 'none';
                return;
            }

            fetch(`/quotes/search-clients?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    clientResults.innerHTML = '';
                    if (data.length === 0) {
                        clientResults.innerHTML = '<div style="padding: 10px 14px; color: #94a3b8; font-size: 13px;">Nenhum cliente encontrado</div>';
                    } else {
                        data.forEach(client => {
                            const item = document.createElement('div');
                            item.style.padding = '10px 14px';
                            item.style.cursor = 'pointer';
                            item.style.fontSize = '13px';
                            item.style.borderBottom = '1px solid #f1f5f9';
                            item.className = 'hover-results';
                            item.innerHTML = `<div style="font-weight: 600; color: #1e293b;">${client.name}</div><div style="font-size: 11px; color: #64748b;">CPF/CNPJ: ${client.document}</div>`;
                            
                            item.addEventListener('click', () => selectClient(client));
                            clientResults.appendChild(item);
                        });
                    }
                    clientResults.style.display = 'block';
                });
        });

        function selectClient(client) {
            hiddenClientId.value = client.id;
            clientCardName.textContent = client.name;
            clientCardDocument.textContent = `CPF/CNPJ: ${client.document}`;
            clientSearchContainer.style.display = 'none';
            clientDetailsCard.style.display = 'block';
            
            clientInput.value = '';
            clientResults.style.display = 'none';
        }

        window.clearSelectedClient = function() {
            hiddenClientId.value = '';
            clientSearchContainer.style.display = 'block';
            clientDetailsCard.style.display = 'none';
            clientInput.focus();
        }

        // Fechar resultados ao clicar fora
        document.addEventListener('click', function(e) {
            if (e.target !== clientInput) {
                clientResults.style.display = 'none';
            }
        });

        // Inicializar com cliente selecionado se houver no form (old)
        @php
            $selectedClient = null;
            $selectedClientId = old('client_id');
            if ($selectedClientId) {
                $selectedClient = $clients->firstWhere('id', $selectedClientId);
            }
        @endphp
        @if($selectedClient)
            selectClient({
                id: "{{ $selectedClient->id }}",
                name: "{!! addslashes($selectedClient->name) !!}",
                document: "{{ $selectedClient->formatted_document }}"
            });
        @endif
    });
</script>

<style>
    .hover-results:hover {
        background-color: #f8fafc !important;
    }
</style>
@endpush
@endsection
