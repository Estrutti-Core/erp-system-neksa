@extends('layouts.app')

@section('title', 'Criar Orçamento')

@section('content')
<div style="max-width: 1000px; margin: 0 auto; padding-bottom: 60px;">
    <!-- Cabeçalho -->
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('quotes.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>
        <h2 style="font-size: 18px; font-weight: 700; color: #0f172a;">Novo Orçamento</h2>
    </div>

    <!-- Formulário Principal -->
    <form method="POST" action="{{ route('quotes.store') }}" id="quote-form">
        @csrf

        <!-- 01. Cliente & Atendimento -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #ede9fe; color: #6d28d9; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">1</div>
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Cliente & Local de Atendimento</h3>
            </div>

            <div class="grid-2 mb-3" style="position: relative;">
                <!-- Autocomplete Cliente -->
                <div class="form-group" style="position: relative;">
                    <label class="form-label">Selecionar Cliente <span style="color:#ef4444">*</span></label>
                    <div style="position: relative;">
                        <input type="text" id="client-search-input" class="form-control" placeholder="Buscar cliente por nome ou documento..." autocomplete="off">
                        <input type="hidden" name="client_id" id="client-id-hidden" required>
                        <div id="client-autocomplete-results" style="display: none; position: absolute; left: 0; right: 0; top: 46px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; z-index: 100; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); max-height: 240px; overflow-y: auto;">
                        </div>
                    </div>
                    <span id="selected-client-badge" style="display: none; margin-top: 8px; font-size: 13px; font-weight: 600; color: #4f46e5;"></span>
                </div>

                <!-- Endereço -->
                <div class="form-group">
                    <label class="form-label" for="client_address_id">Endereço de Atendimento / Entrega <span style="color:#ef4444">*</span></label>
                    <select name="client_address_id" id="client_address_id" class="form-control" required disabled>
                        <option value="">Selecione um cliente primeiro</option>
                    </select>
                </div>
            </div>

            <div class="grid-2 mb-2">
                <div class="form-group">
                    <label class="form-label" for="valid_until">Validade do Orçamento</label>
                    <input type="date" name="valid_until" id="valid_until" class="form-control" value="{{ date('Y-m-d', strtotime('+15 days')) }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="equipment_id">Equipamento <span class="text-xs" style="color: #64748b; font-weight: normal;">(Opcional - Destinado a O.S.)</span></label>
                    <select name="equipment_id" id="equipment_id" class="form-control" disabled style="border-radius: 8px;">
                        <option value="">Selecione um cliente primeiro</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 02. Itens do Orçamento -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">2</div>
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Produtos & Serviços</h3>
            </div>

            <!-- Autocomplete Produto -->
            <div class="form-group" style="position: relative; max-width: 600px; margin-bottom: 24px;">
                <label class="form-label">Pesquisar e Adicionar Item (Produto ou Serviço)</label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4"/>
                    </span>
                    <input type="text" id="product-search-input" class="form-control" placeholder="Buscar por SKU, nome ou código..." style="padding-left: 36px;" autocomplete="off">
                    <div id="product-autocomplete-results" style="display: none; position: absolute; left: 0; right: 0; top: 46px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; z-index: 100; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); max-height: 240px; overflow-y: auto;">
                    </div>
                </div>
            </div>

            <!-- Tabela Dinâmica -->
            <div class="table-wrap mb-4" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                <table id="items-table">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="padding: 10px 14px; width: 45%;">Descrição</th>
                            <th style="width: 15%;">Tipo</th>
                            <th style="width: 12%;">Qtd</th>
                            <th style="width: 16%;">Preço Unit. (R$)</th>
                            <th style="width: 16%;">Total (R$)</th>
                            <th style="width: 6%;"></th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody">
                        <tr id="empty-row">
                            <td colspan="6" style="text-align: center; padding: 32px; color: #94a3b8;">
                                <x-heroicon-o-shopping-cart class="w-10 h-10" style="margin: 0 auto 8px; color: #cbd5e1;"/>
                                <span style="font-size: 13px;">Nenhum produto ou serviço adicionado. Use o campo de pesquisa acima para incluir itens.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totais -->
            <div style="display: flex; justify-content: flex-end;">
                <div style="width: 320px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
                    <div class="flex justify-between items-center mb-2" style="font-size: 13px; color: #64748b;">
                        <span>Subtotal</span>
                        <span id="subtotal-val" style="font-weight: 600; color: #1e293b;">R$ 0,00</span>
                    </div>

                    <div class="form-group flex justify-between items-center mb-3 gap-3" style="font-size: 13px; color: #64748b; margin-bottom: 12px;">
                        <span>Desconto (R$)</span>
                        <input type="text" name="discount_amount" id="discount-val-input" class="form-control money text-right" value="0,00" style="width: 120px; padding: 4px 8px; height: 32px;" oninput="calculateTotals()">
                    </div>

                    <div class="flex justify-between items-center pt-3 border-t" style="font-size: 15px; font-weight: 700; color: #0f172a;">
                        <span>Total Geral</span>
                        <span id="total-val" style="color: #4f46e5; font-size: 20px;">R$ 0,00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 03. Informações Adicionais -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">3</div>
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Observações & Notas</h3>
            </div>

            <div class="form-group mb-3">
                <label class="form-label" for="notes">Observações Públicas (Aparecem na proposta impressa)</label>
                <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Ex: Condições de pagamento, prazos de entrega, garantia..."></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="internal_notes">Notas Internas / Operacionais</label>
                <textarea name="internal_notes" id="internal_notes" class="form-control" rows="2" placeholder="Instruções para o técnico ou histórico de negociação interna..."></textarea>
            </div>
        </div>

        <!-- Ações do Formulário -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('quotes.index') }}" class="btn btn-secondary" style="border-radius: 8px;">Cancelar</a>
            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); border-radius: 8px; padding: 10px 24px;">
                Salvar Orçamento
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    let itemIndex = 0;

    // Autocomplete Clientes
    const clientInput = document.getElementById('client-search-input');
    const clientResults = document.getElementById('client-autocomplete-results');
    const hiddenClientId = document.getElementById('client-id-hidden');
    const selectedClientBadge = document.getElementById('selected-client-badge');
    const selectAddress = document.getElementById('client_address_id');

    clientInput.addEventListener('input', function() {
        const query = this.value;
        if (query.length < 2) {
            clientResults.style.display = 'none';
            return;
        }

        fetch(`{{ route('quotes.search-clients') }}?q=${encodeURIComponent(query)}`)
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
        selectedClientBadge.textContent = `Cliente Selecionado: ${client.name}`;
        selectedClientBadge.style.display = 'block';
        clientInput.value = '';
        clientResults.style.display = 'none';
        
        // Carregar endereços
        selectAddress.disabled = false;
        selectAddress.innerHTML = '<option value="">Carregando endereços...</option>';

        fetch(`{{ url('quotes/client-addresses') }}/${client.id}`)
            .then(res => res.json())
            .then(data => {
                selectAddress.innerHTML = '';
                if (data.length === 0) {
                    selectAddress.innerHTML = '<option value="">Cliente sem endereços cadastrados</option>';
                } else {
                    data.forEach(addr => {
                        const opt = document.createElement('option');
                        opt.value = addr.id;
                        opt.textContent = addr.label;
                        selectAddress.appendChild(opt);
                    });
                }
            });

        // Carregar equipamentos
        loadClientEquipments(client.id);
    }

    function loadClientEquipments(clientId, selectedEquipmentId = null) {
        const selectEquipment = document.getElementById('equipment_id');
        selectEquipment.disabled = false;
        selectEquipment.innerHTML = '<option value="">Carregando equipamentos...</option>';

        fetch(`{{ url('clients') }}/${clientId}/equipments/json`)
            .then(res => res.json())
            .then(data => {
                selectEquipment.innerHTML = '<option value="">Nenhum equipamento</option>';
                if (data.length > 0) {
                    data.forEach(eq => {
                        const opt = document.createElement('option');
                        opt.value = eq.id;
                        let text = eq.name;
                        if (eq.brand) text += ` (${eq.brand})`;
                        if (eq.model) text += ` - ${eq.model}`;
                        if (eq.serial_number) text += ` [S/N: ${eq.serial_number}]`;
                        opt.textContent = text;
                        if (selectedEquipmentId && parseInt(eq.id) === parseInt(selectedEquipmentId)) {
                            opt.selected = true;
                        }
                        selectEquipment.appendChild(opt);
                    });
                }
            });
    }

    // Autocomplete Produtos
    const productInput = document.getElementById('product-search-input');
    const productResults = document.getElementById('product-autocomplete-results');
    const tbody = document.getElementById('items-tbody');
    const emptyRow = document.getElementById('empty-row');

    productInput.addEventListener('input', function() {
        const query = this.value;
        if (query.length < 2) {
            productResults.style.display = 'none';
            return;
        }

        fetch(`{{ route('quotes.search-items') }}?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                productResults.innerHTML = '';
                if (data.length === 0) {
                    productResults.innerHTML = '<div style="padding: 10px 14px; color: #94a3b8; font-size: 13px;">Nenhum item encontrado</div>';
                } else {
                    data.forEach(prod => {
                        const item = document.createElement('div');
                        item.style.padding = '10px 14px';
                        item.style.cursor = 'pointer';
                        item.style.fontSize = '13px';
                        item.style.borderBottom = '1px solid #f1f5f9';
                        item.className = 'hover-results';
                        item.innerHTML = `<div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="font-weight: 600; color: #1e293b;">${prod.name}</span>
                                <span style="font-size:11px; font-family:monospace; color:#64748b; margin-left:6px;">SKU: ${prod.sku}</span>
                            </div>
                            <div style="font-weight:700; color:#4f46e5;">R$ ${prod.sale_price}</div>
                        </div>`;
                        
                        item.addEventListener('click', () => addItem(prod));
                        productResults.appendChild(item);
                    });
                }
                productResults.style.display = 'block';
            });
    });

    function addItem(prod) {
        if (emptyRow) {
            emptyRow.style.display = 'none';
        }

        const tr = document.createElement('tr');
        tr.id = `item-row-${itemIndex}`;
        tr.style.borderBottom = '1px solid #f1f5f9';
        
        tr.innerHTML = `
            <td style="padding: 12px 14px;">
                <input type="hidden" name="items[${itemIndex}][product_id]" value="${prod.type === 'service' ? '' : prod.id}">
                <input type="hidden" name="items[${itemIndex}][service_id]" value="${prod.type === 'service' ? prod.id : ''}">
                <input type="text" name="items[${itemIndex}][description]" class="form-control" value="${prod.name}" style="font-size: 13px; padding: 6px 10px;">
            </td>
            <td>
                <span class="badge badge-${prod.type === 'service' ? 'violet' : 'slate'}">${prod.type_label}</span>
            </td>
            <td>
                <input type="number" name="items[${itemIndex}][quantity]" class="form-control text-center qty-input" value="1" step="1" min="1" style="font-size: 13px; padding: 6px; max-width:80px;" oninput="updateRowTotal(${itemIndex})">
            </td>
            <td>
                <input type="text" name="items[${itemIndex}][unit_price]" class="form-control text-right price-input money" value="${prod.sale_price}" style="font-size: 13px; padding: 6px 10px; font-family: monospace;" oninput="updateRowTotal(${itemIndex})">
            </td>
            <td style="padding: 12px 14px; font-family: monospace; font-weight: 700; color: #1e293b;" class="text-right row-total" id="row-total-${itemIndex}">
                R$ ${prod.sale_price}
            </td>
            <td style="padding: 12px 14px; text-align: center;">
                <button type="button" class="btn btn-danger btn-sm" style="padding: 5px;" onclick="removeItem(${itemIndex})" title="Excluir item">
                    <x-heroicon-o-trash class="w-4 h-4"/>
                </button>
            </td>
        `;

        tbody.appendChild(tr);
        
        // Auto-mask do novo campo de preço unitário
        const newPriceInput = tr.querySelector('.price-input');
        IMask(newPriceInput, {
            mask: 'num',
            blocks: {
                num: {
                    mask: Number,
                    thousandsSeparator: '.',
                    radix: ',',
                    scale: 2,
                    signed: false,
                    padFractionalZeros: true,
                    normalizeZeros: true,
                }
            }
        });

        itemIndex++;
        productInput.value = '';
        productResults.style.display = 'none';

        calculateTotals();
    }

    function removeItem(index) {
        const row = document.getElementById(`item-row-${index}`);
        if (row) {
            row.remove();
        }

        // Se a tabela ficou vazia, mostra a linha informativa
        if (tbody.querySelectorAll('tr:not(#empty-row)').length === 0) {
            emptyRow.style.display = 'table-row';
        }

        calculateTotals();
    }

    function parseFormattedFloat(str) {
        if (!str) return 0;
        return parseFloat(str.replace(/\./g, '').replace(',', '.')) || 0;
    }

    function updateRowTotal(index) {
        const row = document.getElementById(`item-row-${index}`);
        if (!row) return;

        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFormattedFloat(row.querySelector('.price-input').value);
        const total = qty * price;

        row.querySelector('.row-total').textContent = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        calculateTotals();
    }

    function calculateTotals() {
        let subtotal = 0;
        tbody.querySelectorAll('tr:not(#empty-row)').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price = parseFormattedFloat(row.querySelector('.price-input').value);
            subtotal += qty * price;
        });

        const discountInput = document.getElementById('discount-val-input');
        const discount = parseFormattedFloat(discountInput.value);
        const total = Math.max(0, subtotal - discount);

        document.getElementById('subtotal-val').textContent = 'R$ ' + subtotal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('total-val').textContent = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Fechar resultados ao clicar fora
    document.addEventListener('click', function(e) {
        if (e.target !== clientInput) {
            clientResults.style.display = 'none';
        }
        if (e.target !== productInput) {
            productResults.style.display = 'none';
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar mask de desconto
        const discountInput = document.getElementById('discount-val-input');
        IMask(discountInput, {
            mask: 'num',
            blocks: {
                num: {
                    mask: Number,
                    thousandsSeparator: '.',
                    radix: ',',
                    scale: 2,
                    signed: false,
                    padFractionalZeros: true,
                    normalizeZeros: true,
                }
            }
        });
    });
</script>

<style>
    .hover-results:hover {
        background-color: #f8fafc !important;
    }
</style>
@endpush
