@extends('layouts.app')
@section('title', 'Ordens de Serviço')

@section('topbar-actions')
    <a href="{{ route('service-orders.create') }}" class="btn btn-primary btn-sm">+ Nova OS</a>
@endsection

@section('content')

{{-- Filtros --}}
<div class="card mb-4">
    <form method="GET" class="flex gap-3 flex-wrap items-center">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar código, cliente..." class="form-control" style="max-width:240px">
        <select name="status" class="form-control" style="max-width:160px">
            <option value="">Todos os status</option>
            @foreach($statuses as $slug => $name)
                <option value="{{ $slug }}" {{ request('status') == $slug ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
        </select>
        <select name="technician_id" class="form-control" style="max-width:180px">
            <option value="">Todos técnicos</option>
            @foreach($technicians as $t)
                <option value="{{ $t->id }}" {{ request('technician_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
            @endforeach
        </select>
        <input type="date" name="date" value="{{ request('date') }}" class="form-control" style="max-width:160px">
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        @if(request()->hasAny(['search','status','technician_id','date']))
            <a href="{{ route('service-orders.index') }}" class="btn btn-secondary btn-sm">Limpar</a>
        @endif
    </form>
</div>

{{-- Listagem --}}
<div class="card">
    @if($orders->isEmpty())
        <div style="text-align:center;padding:48px;color:#94a3b8">
            <div class="flex justify-center" style="margin-bottom:12px"><x-heroicon-o-clipboard-document-list class="w-10 h-10 text-gray-300"/></div>
            <div style="font-size:16px;font-weight:600;margin-bottom:6px">Nenhuma OS encontrada</div>
            <div class="text-sm">Tente ajustar os filtros ou <a href="{{ route('service-orders.create') }}" style="color:var(--primary);font-weight:600">criar uma nova OS</a>.</div>
        </div>
    @else
        {{-- Mobile: cards --}}
        <div class="hide-desktop" style="display:none">
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
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Técnico</th>
                        <th>Status</th>
                        <th>Prioridade</th>
                        <th>Agendado</th>
                        <th>Valor</th>
                        <th style="text-align: right; padding-right: 20px;">Ações</th>
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
                        <td style="text-align: right; padding-right: 20px;">
                            <div class="flex gap-2 justify-end">
                                <a href="{{ route('service-orders.show', $os) }}" class="btn btn-secondary btn-sm"
                                    style="padding: 6px; border-radius: 6px;" title="Ver detalhes">
                                    <x-heroicon-o-eye class="w-4 h-4" />
                                </a>
                                <a href="{{ route('service-orders.pdf', $os) }}" class="btn btn-secondary btn-sm"
                                    style="padding: 6px; border-radius: 6px;" title="PDF" target="_blank">
                                    <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                </a>
                                @can('update', $os)
                                    <a href="{{ route('service-orders.edit', $os) }}" class="btn btn-secondary btn-sm"
                                        style="padding: 6px; border-radius: 6px;" title="Editar">
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

        <div class="mt-3">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
