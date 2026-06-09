@extends('layouts.app')
@section('title', 'Conferência de Recebimento')

@section('content')
<div style="max-width: 900px; margin: 0 auto; padding-bottom: 40px;">

    <!-- Alertas de Erro -->
    @if($errors->any())
        <div class="card mb-4" style="background:#fef2f2; border:1px solid #fee2e2; color:#b91c1c; padding:16px; border-radius:8px;">
            <ul style="margin:0; padding-left:20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Cabeçalho -->
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('purchase-orders.show', $inventoryConference->purchase_order_id) }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar para Compra
        </a>
        <h2 style="font-size: 18px; font-weight: 700; color: #0f172a;">
            @if($inventoryConference->completed_at)
                Recebimento Físico Realizado
            @else
                Conferência de Recebimento
            @endif
        </h2>
    </div>

    <!-- Informações da Conferência -->
    <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; background:#f8fafc;">
        <div class="grid-3">
            <div>
                <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Pedido de Compra</span>
                <p style="font-size: 14px; font-weight: 600; color: #1e293b; margin: 2px 0 0 0;">{{ $inventoryConference->purchaseOrder->code }}</p>
            </div>
            <div>
                <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Fornecedor</span>
                <p style="font-size: 14px; font-weight: 600; color: #1e293b; margin: 2px 0 0 0;">{{ $inventoryConference->purchaseOrder->supplier->name }}</p>
            </div>
            <div>
                <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Conferente / Responsável</span>
                <p style="font-size: 14px; font-weight: 600; color: #1e293b; margin: 2px 0 0 0;">{{ $inventoryConference->checker->name }}</p>
            </div>
        </div>
    </div>

    <!-- Formulário de Contagem -->
    <form method="POST" action="{{ route('inventory-conferences.complete', $inventoryConference) }}">
        @csrf

        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <h3 style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                Contagem Física dos Produtos
            </h3>

            <div class="table-wrap mb-4">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Produto</th>
                            <th style="text-align: right; width: 25%;">Qtd Esperada (Pendente)</th>
                            <th style="text-align: right; width: 25%;">Qtd Recebida Fisicamente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inventoryConference->items as $item)
                        <tr>
                            <td>
                                <div class="font-semibold">{{ $item->product->name }}</div>
                                <div class="text-xs text-muted font-mono">SKU: {{ $item->product->sku }}</div>
                            </td>
                            <td style="text-align: right; font-weight: 600;" class="text-sm">
                                {{ number_format($item->expected_quantity, 3, ',', '.') }}
                            </td>
                            <td style="text-align: right;">
                                @if($inventoryConference->completed_at)
                                    <span style="font-size: 15px; font-weight: 700; color:var(--primary);">
                                        {{ number_format($item->quantity_received, 3, ',', '.') }}
                                    </span>
                                @else
                                    <input type="number" step="0.001" name="counts[{{ $item->id }}]" value="{{ old('counts.'.$item->id, $item->expected_quantity) }}" class="form-control text-right" style="max-width: 150px; display: inline-block; font-size:15px; font-weight:bold; height: 44px; text-align: right;" required min="0">
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Observações -->
            <div class="form-group mb-0" style="border-top:1px solid #f1f5f9; padding-top:16px;">
                <label class="form-label" for="notes">Observações do Recebimento</label>
                @if($inventoryConference->completed_at)
                    <p style="background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size:13px; color:#475569; margin: 4px 0 0 0;">
                        {{ $inventoryConference->notes ?: 'Nenhuma observação registrada.' }}
                    </p>
                @else
                    <textarea id="notes" name="notes" rows="3" class="form-control" placeholder="Divergências, avarias, número da nota fiscal de entrada...">{{ old('notes', $inventoryConference->notes) }}</textarea>
                @endif
            </div>
        </div>

        @if(!$inventoryConference->completed_at)
            <!-- Ações -->
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('purchase-orders.show', $inventoryConference->purchase_order_id) }}" class="btn btn-secondary" style="border-radius: 8px;">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%); border-radius: 8px; padding: 12px 28px; font-weight:700;">
                    Concluir Recebimento & Atualizar Estoque
                </button>
            </div>
        @endif
    </form>
</div>
@endsection
