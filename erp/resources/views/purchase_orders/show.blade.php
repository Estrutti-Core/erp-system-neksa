@extends('layouts.app')
@section('title', "Pedido " . $purchaseOrder->code)

@section('content')
<div style="max-width: 1000px; margin: 0 auto; padding-bottom: 40px;">

    <!-- Alertas de Erro/Sucesso -->
    @if($errors->any())
        <div class="card mb-4" style="background:#fef2f2; border:1px solid #fee2e2; color:#b91c1c; padding:16px; border-radius:8px;">
            <ul style="margin:0; padding-left:20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Cabeçalho de Ações -->
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>
        <div class="flex gap-2 flex-wrap">
            @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::Draft)
                @can('update', $purchaseOrder)
                    <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="btn btn-secondary btn-sm">Editar Rascunho</a>
                    
                    <form action="{{ route('purchase-orders.order', $purchaseOrder) }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);">
                            Emitir Pedido
                        </button>
                    </form>
                @endcan
                
                @can('delete', $purchaseOrder)
                    <form action="{{ route('purchase-orders.destroy', $purchaseOrder) }}" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este rascunho?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-secondary btn-sm" style="color:#ef4444; border-color:#fee2e2;">
                            Excluir
                        </button>
                    </form>
                @endcan
            @endif

            @if(in_array($purchaseOrder->status, [\App\Enums\PurchaseOrderStatus::Ordered, \App\Enums\PurchaseOrderStatus::PartiallyReceived]))
                @can('update', $purchaseOrder)
                    <form action="{{ route('inventory-conferences.store') }}" method="POST" style="display:inline;">
                        @csrf
                        <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">
                        <button type="submit" class="btn btn-primary btn-sm" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
                            Iniciar Recebimento Físico
                        </button>
                    </form>

                    <form action="{{ route('purchase-orders.cancel', $purchaseOrder) }}" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja cancelar este pedido de compra?')">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm" style="color:#ef4444; border-color:#fee2e2;">
                            Cancelar Pedido
                        </button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    <!-- Grid de Informações -->
    <div class="grid-3 mb-4" style="gap:16px;">
        <!-- Card Dados do Pedido -->
        <div class="card shadow-sm" style="border-radius:12px; border:1px solid #e2e8f0; padding:20px; grid-column: span 2;">
            <div class="flex justify-between items-center mb-4 pb-2" style="border-bottom:1px solid #f1f5f9;">
                <span class="font-mono text-lg font-bold" style="color:#0f172a;">{{ $purchaseOrder->code }}</span>
                <span class="badge badge-{{ $purchaseOrder->status->color() }}" style="font-size: 13px; padding: 6px 12px;">
                    {{ $purchaseOrder->status->label() }}
                </span>
            </div>

            <div class="grid-2">
                <div>
                    <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Fornecedor</span>
                    <p style="font-size: 14px; font-weight: 600; color: #1e293b; margin: 2px 0 12px 0;">
                        <a href="{{ route('suppliers.show', $purchaseOrder->supplier) }}" style="color:var(--primary); text-decoration:underline;">
                            {{ $purchaseOrder->supplier->name }}
                        </a>
                    </p>

                    <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">CNPJ / CPF</span>
                    <p style="font-size: 13px; color: #334155; margin: 2px 0 12px 0; font-family: monospace;">{{ $purchaseOrder->supplier->document ?: '—' }}</p>
                </div>
                <div>
                    <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Emissão</span>
                    <p style="font-size: 13px; color: #334155; margin: 2px 0 12px 0;">{{ $purchaseOrder->created_at->format('d/m/Y H:i') }}</p>

                    <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Comprador</span>
                    <p style="font-size: 13px; color: #334155; margin: 2px 0 12px 0;">{{ $purchaseOrder->creator->name ?? '—' }}</p>
                </div>
            </div>
        </div>

        <!-- Card Total -->
        <div class="card shadow-sm flex flex-col justify-between" style="border-radius:12px; border:1px solid #e2e8f0; padding:20px; background:#f8fafc;">
            <div>
                <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Valor Total</span>
                <p style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 4px 0 0 0;">
                    R$ {{ number_format($purchaseOrder->total_amount, 2, ',', '.') }}
                </p>
            </div>
            <div style="border-top:1px solid #e2e8f0; padding-top:12px; margin-top:12px;">
                <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; display:block; margin-bottom:4px;">Progresso de Recebimento</span>
                @php
                    $totalPedida = $purchaseOrder->items->sum('quantity');
                    $totalRecebida = $purchaseOrder->items->sum(function($item) use ($pendingBalances) {
                        return $item->quantity - ($pendingBalances[$item->product_id] ?? 0);
                    });
                    $percentage = $totalPedida > 0 ? min(100, round(($totalRecebida / $totalPedida) * 100)) : 0;
                @endphp
                <div style="width:100%; background:#e2e8f0; border-radius:10px; height:8px; overflow:hidden;">
                    <div style="width:{{ $percentage }}%; background:#10b981; height:100%;"></div>
                </div>
                <div class="flex justify-between text-xs mt-1" style="color:#64748b; font-weight:600;">
                    <span>Concluído</span>
                    <span>{{ $percentage }}%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Itens do Pedido -->
    <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
            Itens da Compra
        </h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th style="text-align: right;">Qtd Pedida</th>
                        <th style="text-align: right;">Qtd Pendente</th>
                        <th style="text-align: right;">Custo Unitário</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchaseOrder->items as $item)
                    <tr>
                        <td>
                            <div class="font-semibold">{{ $item->description }}</div>
                            <div class="text-xs text-muted font-mono">SKU: {{ $item->product->sku }}</div>
                        </td>
                        <td class="text-sm font-semibold" style="text-align: right;">
                            {{ number_format($item->quantity, 3, ',', '.') }} {{ $item->unit }}
                        </td>
                        <td class="text-sm" style="text-align: right; font-weight: 700; color: {{ ($pendingBalances[$item->product_id] ?? 0) > 0 ? '#d97706' : '#10b981' }}">
                            {{ number_format($pendingBalances[$item->product_id] ?? 0, 3, ',', '.') }} {{ $item->unit }}
                        </td>
                        <td class="text-sm font-mono" style="text-align: right;">
                            R$ {{ number_format($item->unit_cost, 2, ',', '.') }}
                        </td>
                        <td class="text-sm font-semibold font-mono" style="text-align: right;">
                            R$ {{ number_format($item->total_cost, 2, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Histórico de Conferências -->
    <div class="card shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
            Conferências de Recebimento Físico
        </h3>

        @if($purchaseOrder->inventoryConferences->isEmpty())
            <div style="text-align: center; padding: 16px; color: #94a3b8; font-size:14px;">
                Nenhum recebimento físico iniciado ou concluído para esta compra.
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Conferente</th>
                            <th>Status</th>
                            <th>Observações</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchaseOrder->inventoryConferences as $conf)
                        <tr>
                            <td class="text-sm">{{ $conf->created_at->format('d/m/Y H:i') }}</td>
                            <td class="text-sm font-semibold">{{ $conf->checker->name }}</td>
                            <td>
                                @if($conf->completed_at)
                                    <span class="badge badge-green">Concluído</span>
                                @else
                                    <span class="badge badge-yellow">Pendente / Em Contagem</span>
                                @endif
                            </td>
                            <td class="text-sm text-muted">{{ $conf->notes ?: '—' }}</td>
                            <td>
                                <a href="{{ route('inventory-conferences.show', $conf) }}" class="btn btn-secondary btn-sm">
                                    {{ $conf->completed_at ? 'Ver Detalhes' : 'Digitar Contagem' }}
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection
