@extends('layouts.app')

@section('title', 'Contas a Receber')

@section('topbar-actions')
    <a href="{{ route('receivables.export.xlsx', request()->all()) }}" class="btn btn-success flex items-center gap-2" style="background-color: #16a34a; border-color: #16a34a; color: white;">
        <x-heroicon-o-document-arrow-down class="w-5 h-5"/> Exportar (XLSX)
    </a>
    <a href="{{ route('receivables.create') }}" class="btn btn-primary">
        <x-heroicon-o-plus class="w-5 h-5"/> Novo Título
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
        <form method="GET" action="{{ route('receivables.index') }}" id="filter-form">
            <div class="sticky-header-table">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Competência</th>
                            <th>Valor Total</th>
                            <th>Valor Líquido</th>
                            <th>Status</th>
                            <th style="width:120px; text-align:right">Ações</th>
                        </tr>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <td>
                                <input type="text" name="search" class="form-control" placeholder="Buscar..." value="{{ request('search') }}" style="font-size: 11px; padding: 4px 8px; height: auto;">
                            </td>
                            <td>
                                <span class="text-xs text-slate-400">Busca no código/cliente</span>
                            </td>
                            <td>
                                <div class="flex gap-1 items-center">
                                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" style="font-size: 10px; padding: 2px; height: auto; width: 105px;">
                                    <span class="text-xs text-slate-400">à</span>
                                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" style="font-size: 10px; padding: 2px; height: auto; width: 105px;">
                                </div>
                            </td>
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
                                    <a href="{{ route('receivables.index') }}" class="btn btn-secondary btn-sm" style="padding: 4px 8px;" title="Limpar Filtros">
                                        <x-heroicon-o-x-mark class="w-4 h-4"/>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($receivables as $rec)
                            <tr>
                                <td><strong>{{ $rec->code }}</strong></td>
                                <td>{{ $rec->client->name ?? 'Cliente Avulso' }}</td>
                                <td>{{ \Carbon\Carbon::parse($rec->competence_date)->format('d/m/Y') }}</td>
                                <td>R$ {{ number_format($rec->total_amount, 2, ',', '.') }}</td>
                                <td>R$ {{ number_format($rec->net_amount, 2, ',', '.') }}</td>
                                <td>
                                    @php
                                        $colorClass = match($rec->status) {
                                            \App\Enums\PaymentStatus::Pending => 'bg-amber-100 text-amber-800',
                                            \App\Enums\PaymentStatus::Paid => 'bg-green-100 text-green-800',
                                            \App\Enums\PaymentStatus::PartiallyPaid => 'bg-blue-100 text-blue-800',
                                            \App\Enums\PaymentStatus::Cancelled => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                    @endphp
                                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $colorClass }}">
                                        {{ $rec->status->label() }}
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <a href="{{ route('receivables.show', $rec) }}" class="btn btn-secondary btn-sm" style="padding: 4px 8px;" title="Ver Detalhes">
                                        <x-heroicon-o-eye class="w-4 h-4"/>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align:center; padding:32px; color:var(--text-secondary)">
                                    Nenhum título a receber encontrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        @if($receivables->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $receivables->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
