@extends('layouts.app')
@section('title', 'Editar Pedido de Compra')

@section('content')
<div style="max-width: 900px; margin: 0 auto; padding-bottom: 40px;">
    <!-- Cabeçalho -->
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>
        <h2 style="font-size: 18px; font-weight: 700; color: #0f172a;">Editar Pedido de Compra ({{ $purchaseOrder->code }})</h2>
    </div>

    <form method="POST" action="{{ route('purchase-orders.update', $purchaseOrder) }}" id="order-form">
        @csrf
        @method('PUT')

        <!-- Dados Gerais -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div class="form-group mb-0">
                <label class="form-label" for="supplier_id">Fornecedor <span style="color:#ef4444">*</span></label>
                <select id="supplier_id" name="supplier_id" class="form-control @error('supplier_id') is-invalid @enderror" required>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ old('supplier_id', $purchaseOrder->supplier_id) == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->name }} ({{ $supplier->document }})
                        </option>
                    @endforeach
                </select>
                @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <!-- Itens do Pedido -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div class="flex items-center justify-between mb-4 pb-2" style="border-bottom: 1px solid #f1f5f9;">
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b; margin: 0;">Itens da Compra</h3>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addItemRow()">
                    + Adicionar Produto
                </button>
            </div>

            <div class="table-wrap mb-4">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 45%;">Produto</th>
                            <th style="width: 15%;">Quantidade</th>
                            <th style="width: 20%;">Custo Unitário (R$)</th>
                            <th style="width: 15%;">Subtotal (R$)</th>
                            <th style="width: 5%;"></th>
                        </tr>
                    </thead>
                    <tbody id="items-container">
                        @foreach($purchaseOrder->items as $idx => $item)
                        <tr id="item-row-{{ $idx }}">
                            <td>
                                <select name="items[{{ $idx }}][product_id]" class="form-control item-product" onchange="onProductSelect({{ $idx }})" required>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}" data-cost="{{ $p->cost_price }}" {{ $item->product_id == $p->id ? 'selected' : '' }}>
                                            {{ $p->name }} (SKU: {{ $p->sku }})
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.001" name="items[{{ $idx }}][quantity]" value="{{ $item->quantity }}" class="form-control item-qty" oninput="calculateRowTotal({{ $idx }})" required min="0.001">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="items[{{ $idx }}][unit_cost]" value="{{ $item->unit_cost }}" class="form-control item-cost" oninput="calculateRowTotal({{ $idx }})" required min="0.00">
                            </td>
                            <td>
                                <span class="item-subtotal font-mono text-sm font-semibold">R$ {{ number_format($item->total_cost, 2, ',', '.') }}</span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-secondary btn-sm" style="color:#ef4444; border-color:#fee2e2; padding: 4px 8px;" onclick="removeItemRow({{ $idx }})">
                                    X
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Totalizador -->
            <div class="flex justify-end items-center gap-3" style="border-top: 1px solid #f1f5f9; padding-top: 16px;">
                <span style="font-size: 14px; font-weight: 700; color: #475569;">Valor Total do Pedido:</span>
                <span id="order-total" style="font-size: 18px; font-weight: 800; color: var(--primary);">R$ 0,00</span>
            </div>
        </div>

        <!-- Ações do Formulário -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-secondary" style="border-radius: 8px;">Cancelar</a>
            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); border-radius: 8px; padding: 10px 24px;">
                Salvar Alterações
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    let itemIndex = {{ $purchaseOrder->items->count() }};
    const products = @json($products);

    function addItemRow(item = {}) {
        const container = document.getElementById('items-container');
        const row = document.createElement('tr');
        row.id = `item-row-${itemIndex}`;

        let options = '<option value="">Selecione um produto</option>';
        products.forEach(p => {
            const selected = (item.product_id == p.id) ? 'selected' : '';
            options += `<option value="${p.id}" data-cost="${p.cost_price}" ${selected}>${p.name} (SKU: ${p.sku})</option>`;
        });

        row.innerHTML = `
            <td>
                <select name="items[${itemIndex}][product_id]" class="form-control item-product" onchange="onProductSelect(${itemIndex})" required>
                    ${options}
                </select>
            </td>
            <td>
                <input type="number" step="0.001" name="items[${itemIndex}][quantity]" value="${item.quantity || '1'}" class="form-control item-qty" oninput="calculateRowTotal(${itemIndex})" required min="0.001">
            </td>
            <td>
                <input type="number" step="0.01" name="items[${itemIndex}][unit_cost]" value="${item.unit_cost || '0.00'}" class="form-control item-cost" oninput="calculateRowTotal(${itemIndex})" required min="0.00">
            </td>
            <td>
                <span class="item-subtotal font-mono text-sm font-semibold">R$ 0,00</span>
            </td>
            <td>
                <button type="button" class="btn btn-secondary btn-sm" style="color:#ef4444; border-color:#fee2e2; padding: 4px 8px;" onclick="removeItemRow(${itemIndex})">
                    X
                </button>
            </td>
        `;

        container.appendChild(row);
        calculateRowTotal(itemIndex);
        itemIndex++;
    }

    function removeItemRow(index) {
        const row = document.getElementById(`item-row-${index}`);
        if (row) {
            row.remove();
            calculateOrderTotal();
        }
    }

    function onProductSelect(index) {
        const row = document.getElementById(`item-row-${index}`);
        const select = row.querySelector('.item-product');
        const costInput = row.querySelector('.item-cost');
        
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption) {
            const cost = selectedOption.getAttribute('data-cost');
            if (cost) costInput.value = parseFloat(cost).toFixed(2);
        }
        calculateRowTotal(index);
    }

    function calculateRowTotal(index) {
        const row = document.getElementById(`item-row-${index}`);
        if (!row) return;

        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const cost = parseFloat(row.querySelector('.item-cost').value) || 0;
        const subtotalSpan = row.querySelector('.item-subtotal');

        const total = qty * cost;
        subtotalSpan.innerText = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        calculateOrderTotal();
    }

    function calculateOrderTotal() {
        let orderTotal = 0;
        document.querySelectorAll('#items-container tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const cost = parseFloat(row.querySelector('.item-cost').value) || 0;
            orderTotal += (qty * cost);
        });

        document.getElementById('order-total').innerText = 'R$ ' + orderTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    document.addEventListener('DOMContentLoaded', function() {
        calculateOrderTotal();
    });
</script>
@endpush
