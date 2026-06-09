@extends('layouts.app')
@section('title', 'Ordens de Serviço')

@section('topbar-actions')
    <a href="{{ route('service-orders.export.xlsx', request()->all()) }}" class="btn btn-success flex items-center gap-2" style="background-color: #16a34a; border-color: #16a34a; color: white;">
        <x-heroicon-o-document-arrow-down class="w-5 h-5"/> Exportar (XLSX)
    </a>
    <a href="{{ route('service-orders.create') }}" class="btn btn-primary">+ Nova OS</a>
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

{{-- Busca Simplificada apenas para Mobile --}}
<div class="card mb-4 hide-desktop">
    <form method="GET" action="{{ route('service-orders.index') }}" class="flex gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar código, cliente..." class="form-control flex-1" style="height: auto; padding: 8px 12px;">
        <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">Buscar</button>
    </form>
</div>

{{-- Listagem --}}
<div class="card">
    <div class="card-body p-0">
        @if($orders->isEmpty())
            <div style="text-align:center;padding:48px;color:#94a3b8">
                <div class="flex justify-center" style="margin-bottom:12px"><x-heroicon-o-clipboard-document-list class="w-10 h-10 text-gray-300"/></div>
                <div style="font-size:16px;font-weight:600;margin-bottom:6px">Nenhuma OS encontrada</div>
                <div class="text-sm">Tente ajustar os filtros ou <a href="{{ route('service-orders.create') }}" style="color:var(--primary);font-weight:600">criar uma nova OS</a>.</div>
            </div>
        @else
            {{-- Mobile: cards --}}
            <div class="hide-desktop p-4">
                @foreach($orders as $os)
                    <a href="{{ route('service-orders.show', $os) }}" style="display:block;text-decoration:none;color:inherit;border-bottom:1px solid #f1f5f9;padding:14px 0">
                        <div class="flex justify-between items-center">
                            <span style="font-size:12px;font-weight:700;color:#64748b">{{ $os->code }}</span>
                            <span class="badge badge-{{ $os->status->color }}">{{ $os->status->name }}</span>
                        </div>
                        <div style="font-weight:600;font-size:14px;margin-top:4px">{{ $os->client->name }}</div>
                        <div class="text-xs text-muted mt-1">
                            {{ $os->clientAddress?->city ?? '—' }}
                            @if($os->technician) · {{ $os->technician->name }} @endif
                            @if($os->scheduled_at) · {{ $os->scheduled_at->format('d/m H:i') }} @endif
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Desktop: table --}}
            <form method="GET" action="{{ route('service-orders.index') }}" id="filter-form" class="hide-mobile">
                <div class="sticky-header-table">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Cliente</th>
                                <th>Técnico</th>
                                <th>Status</th>
                                <th>Prioridade</th>
                                <th>Agendado</th>
                                <th>Valor</th>
                                <th style="width:140px; text-align: right;">Ações</th>
                            </tr>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <td>
                                    <input type="text" name="search" class="form-control" placeholder="Buscar..." value="{{ request('search') }}" style="font-size: 11px; padding: 4px 8px; height: auto;">
                                </td>
                                <td>
                                    <span class="text-xs text-slate-400">Busca no código/cliente</span>
                                </td>
                                <td>
                                    <select name="technician_id" class="form-control" style="font-size: 11px; padding: 4px 8px; height: auto;" onchange="this.form.submit()">
                                        <option value="">Técnicos</option>
                                        @foreach($technicians as $t)
                                            <option value="{{ $t->id }}" {{ request('technician_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="status" class="form-control" style="font-size: 11px; padding: 4px 8px; height: auto;" onchange="this.form.submit()">
                                        <option value="">Status</option>
                                        @foreach($statuses as $slug => $name)
                                            <option value="{{ $slug }}" {{ request('status') == $slug ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td></td>
                                <td>
                                    <input type="date" name="date" value="{{ request('date') }}" class="form-control" style="font-size: 11px; padding: 4px 8px; height: auto;" onchange="this.form.submit()">
                                </td>
                                <td></td>
                                <td style="text-align:right">
                                    <div class="flex gap-1 justify-end">
                                        <button type="submit" class="btn btn-primary btn-sm" style="padding: 4px 8px;" title="Filtrar">
                                            <x-heroicon-o-funnel class="w-4 h-4"/>
                                        </button>
                                        <a href="{{ route('service-orders.index') }}" class="btn btn-secondary btn-sm" style="padding: 4px 8px;" title="Limpar Filtros">
                                            <x-heroicon-o-x-mark class="w-4 h-4"/>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $os)
                            <tr>
                                <td><span style="font-weight:700;font-size:13px;color:#4f46e5">{{ $os->code }}</span></td>
                                <td>
                                    <div style="font-weight:600">{{ $os->client->name }}</div>
                                    <div class="text-xs text-muted">{{ $os->clientAddress?->city }}/{{ $os->clientAddress?->state }}</div>
                                </td>
                                <td>{{ $os->technician?->name ?? '—' }}</td>
                                <td><span class="badge badge-{{ $os->status->color }}">{{ $os->status->name }}</span></td>
                                <td><span class="badge badge-{{ $os->priority->color() }}">{{ $os->priority->label() }}</span></td>
                                <td class="text-sm">{{ $os->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="font-semibold">R$ {{ number_format($os->total_amount, 2, ',', '.') }}</td>
                                <td style="text-align: right;">
                                    <div class="flex gap-1 justify-end">
                                        <a href="{{ route('service-orders.show', $os) }}" class="btn btn-secondary btn-sm"
                                            style="padding: 6px;" title="Ver detalhes">
                                            <x-heroicon-o-eye class="w-4 h-4" />
                                        </a>
                                        <a href="{{ route('service-orders.pdf', $os) }}" class="btn btn-secondary btn-sm"
                                            style="padding: 6px;" title="PDF" target="_blank">
                                            <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                        </a>
                                        @can('update', $os)
                                            <a href="{{ route('service-orders.edit', $os) }}" class="btn btn-secondary btn-sm"
                                                style="padding: 6px;" title="Editar">
                                                <x-heroicon-o-pencil class="w-4 h-4" />
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            @if($orders->hasPages())
                <div class="p-4 border-t border-slate-200">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
