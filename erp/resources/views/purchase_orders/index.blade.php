@extends('layouts.app')
@section('title', 'Pedidos de Compra')
@section('topbar-actions')
    @can('create', App\Models\PurchaseOrder::class)
        <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary btn-sm">+ Novo Pedido</a>
    @endcan
@endsection

@section('content')
<div class="card mb-4">
    <form method="GET" class="flex gap-3 flex-wrap items-center">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cód, Fornecedor..." class="form-control" style="max-width:280px">
        <select name="status" class="form-control" style="max-width:180px">
            <option value="">Todos os Status</option>
            @foreach(\App\Enums\PurchaseOrderStatus::cases() as $status)
                <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        @if(request()->hasAny(['search', 'status']))
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-secondary btn-sm">Limpar</a>
        @endif
    </form>
</div>

<div class="card">
    @if($orders->isEmpty())
        <div style="text-align:center;padding:48px;color:#94a3b8">
            <div class="flex justify-center" style="margin-bottom:12px"><x-heroicon-o-shopping-bag class="w-10 h-10 text-gray-300"/></div>
            <div style="font-size:16px;font-weight:600;margin-bottom:6px">Nenhum pedido de compra encontrado</div>
            <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary mt-3">+ Criar Pedido de Compra</a>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Fornecedor</th>
                        <th>Status</th>
                        <th>Valor Total</th>
                        <th>Emissão</th>
                        <th>Comprador</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                    <tr>
                        <td class="font-mono text-sm font-semibold">{{ $order->code }}</td>
                        <td>
                            <div class="font-semibold">{{ $order->supplier->name }}</div>
                            <div class="text-xs text-muted font-mono">{{ $order->supplier->document }}</div>
                        </td>
                        <td>
                            <span class="badge badge-{{ $order->status->color() }}">{{ $order->status->label() }}</span>
                        </td>
                        <td class="text-sm font-semibold">
                            R$ {{ number_format($order->total_amount, 2, ',', '.') }}
                        </td>
                        <td class="text-sm">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-sm text-muted">{{ $order->creator->name ?? '—' }}</td>
                        <td>
                            <div class="flex gap-2">
                                <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-secondary btn-sm">Ver</a>
                                @if($order->status === \App\Enums\PurchaseOrderStatus::Draft)
                                    @can('update', $order)
                                        <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-secondary btn-sm">Editar</a>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
