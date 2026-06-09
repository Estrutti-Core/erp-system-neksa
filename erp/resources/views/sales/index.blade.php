@extends('layouts.app')

@section('title', 'Vendas')

@section('topbar-actions')
    <a href="{{ route('sales.export.xlsx', request()->all()) }}" class="btn btn-success flex items-center gap-2" style="background-color: #16a34a; border-color: #16a34a; color: white;">
        <x-heroicon-o-document-arrow-down class="w-5 h-5"/> Exportar (XLSX)
    </a>
@endsection

@section('content')
<style>
    .sticky-header-table {
        max-height: 650px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }
    .sticky-header-table thead th {
        position: sticky;
        top: 0;
        background-color: #f8fafc;
        z-index: 10;
        box-shadow: inset 0 -1px 0 #e2e8f0;
    }
</style>

<div class="card">
    <div class="card-body p-0">
        <form method="GET" action="{{ route('sales.index') }}" id="filter-form">
            <div class="sticky-header-table">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Data Emissão</th>
                            <th>Valor Itens</th>
                            <th>Desconto</th>
                            <th>Valor Total</th>
                            <th>Status</th>
                            <th style="width:120px; text-align:right">Ações</th>
                        </tr>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <td>
                                <input type="text" name="search" class="form-control" placeholder="Buscar..." value="{{ request('search') }}" style="font-size: 11px; padding: 4px 8px; height: auto;">
                            </td>
                            <td>
                                <span class="text-xs text-slate-400">Filtro no código/cliente</span>
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>
                                <select name="status" class="form-control" style="font-size: 11px; padding: 4px 8px; height: auto;" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    @foreach($statuses as $status)
                                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="text-align:right">
                                <div class="flex gap-1 justify-end">
                                    <button type="submit" class="btn btn-primary btn-sm" style="padding: 4px 8px;" title="Filtrar">
                                        <x-heroicon-o-funnel class="w-4 h-4"/>
                                    </button>
                                    <a href="{{ route('sales.index') }}" class="btn btn-secondary btn-sm" style="padding: 4px 8px;" title="Limpar Filtros">
                                        <x-heroicon-o-x-mark class="w-4 h-4"/>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                            <tr>
                                <td><strong style="color: #10b981; font-family: monospace;">{{ $sale->code }}</strong></td>
                                <td>
                                    <div>
                                        <div style="font-weight: 600; color: #1e293b;">{{ $sale->client->name }}</div>
                                        <div style="font-size: 11px; color: #64748b;">CPF/CNPJ: {{ $sale->client->formatted_document }}</div>
                                    </div>
                                </td>
                                <td>{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                                <td style="font-family: monospace;">R$ {{ number_format($sale->items_amount, 2, ',', '.') }}</td>
                                <td style="font-family: monospace; color: #ef4444;">
                                    @if($sale->discount_amount > 0)
                                        - R$ {{ number_format($sale->discount_amount, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td style="font-weight: 700; font-family: monospace;">R$ {{ number_format($sale->total_amount, 2, ',', '.') }}</td>
                                <td>
                                    <span class="badge badge-{{ $sale->status->color() }}">
                                        {{ $sale->status->label() }}
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <div class="flex gap-1 justify-end">
                                        <a href="{{ route('sales.show', $sale) }}" class="btn btn-secondary btn-sm" style="padding: 6px;" title="Ver detalhes">
                                            <x-heroicon-o-eye class="w-4 h-4" />
                                        </a>
                                        <a href="{{ route('sales.pdf', $sale) }}" class="btn btn-secondary btn-sm" style="padding: 6px;" title="PDF" target="_blank">
                                            <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 32px;" class="text-slate-500">
                                    Nenhuma venda encontrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        @if($sales->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $sales->links() }}
            </div>
        @endif
    </div>
</div>
@endsection