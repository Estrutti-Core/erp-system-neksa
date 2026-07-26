@extends('layouts.app')
@section('title', 'Ordens de Serviço')

@section('topbar-actions')
    <a href="{{ route('service-orders.export.xlsx', request()->all()) }}" class="btn btn-success flex items-center gap-2" style="background-color: #16a34a; border-color: #16a34a; color: white; border-radius: 8px;">
        <x-heroicon-o-document-arrow-down class="w-4 h-4"/> Exportar (XLSX)
    </a>
    <a href="{{ route('service-orders.create') }}" class="btn btn-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); border-radius: 8px;">
        <x-heroicon-o-plus class="w-4 h-4" /> Nova OS
    </a>
@endsection

@section('content')
    <div style="max-width: 1200px; margin: 0 auto;">
        <!-- Filtros Ampliados -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; padding: 16px;">
            <form method="GET" action="{{ route('service-orders.index') }}"
                class="flex flex-col gap-3">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                    <!-- Busca -->
                    <div style="position: relative;">
                        <label class="form-label" style="font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">Buscar Código/Cliente</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;">
                                <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                placeholder="Buscar..." style="padding-left: 36px; border-radius: 8px;">
                        </div>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="form-label" style="font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">Status</label>
                        <select name="status" class="form-control" style="border-radius: 8px;" onchange="this.form.submit()">
                            <option value="">Todos os status</option>
                            @foreach($statuses as $slug => $name)
                                <option value="{{ $slug }}" {{ request('status') == $slug ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Técnico -->
                    <div>
                        <label class="form-label" style="font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">Técnico</label>
                        <select name="technician_id" class="form-control" style="border-radius: 8px;" onchange="this.form.submit()">
                            <option value="">Todos os técnicos</option>
                            @foreach($technicians as $t)
                                <option value="{{ $t->id }}" {{ request('technician_id') == $t->id ? 'selected' : '' }}>
                                    {{ $t->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Prioridade -->
                    <div>
                        <label class="form-label" style="font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">Prioridade</label>
                        <select name="priority" class="form-control" style="border-radius: 8px;" onchange="this.form.submit()">
                            <option value="">Todas as prioridades</option>
                            @foreach(\App\Enums\ServiceOrderPriority::cases() as $p)
                                <option value="{{ $p->value }}" {{ request('priority') == $p->value ? 'selected' : '' }}>
                                    {{ $p->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Data Agendamento -->
                    <div>
                        <label class="form-label" style="font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">Data de Agendamento</label>
                        <input type="date" name="date" value="{{ request('date') }}" class="form-control" style="border-radius: 8px;" onchange="this.form.submit()">
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm" style="border-radius: 8px;">Filtrar</button>
                    @if(request()->anyFilled(['search', 'status', 'technician_id', 'priority', 'date']))
                        <a href="{{ route('service-orders.index') }}" class="btn btn-secondary btn-sm"
                            style="border-radius: 8px; color: #ef4444;">Limpar</a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Listagem -->
        <div class="card shadow-sm" style="border-radius: 12px; padding: 0; overflow: hidden; border: 1px solid #e2e8f0;">
            @if($orders->isEmpty())
                <div style="text-align:center;padding:48px;color:#94a3b8">
                    <div class="flex justify-center" style="margin-bottom:12px">
                        <x-heroicon-o-wrench-screwdriver class="w-10 h-10 text-gray-300"/>
                    </div>
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
                <div class="table-wrap hide-mobile">
                    <table>
                        <thead>
                            <tr>
                                <th style="font-weight: 600; padding: 14px 20px;">Código</th>
                                <th>Cliente</th>
                                <th>Técnico</th>
                                <th>Status</th>
                                <th>Prioridade</th>
                                <th>Agendado</th>
                                <th>Valor Total</th>
                                <th style="text-align: right; padding-right: 20px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $os)
                                <tr>
                                    <td style="padding: 14px 20px; font-weight: 700; color: #4f46e5; font-family: monospace;">
                                        {{ $os->code }}
                                    </td>
                                    <td>
                                        <div>
                                            <div style="font-weight: 600; color: #1e293b;">{{ $os->client->name }}</div>
                                            <div style="font-size: 11px; color: #64748b;">CPF/CNPJ:
                                                {{ $os->client->formatted_document }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight: 500; color: #475569;">
                                            {{ $os->technician?->name ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $os->status->color }}">
                                            {{ $os->status->name }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $os->priority->color() }}">
                                            {{ $os->priority->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-weight: 500; color: #475569;">
                                            {{ $os->scheduled_at?->format('d/m/Y H:i') ?? '—' }}
                                        </span>
                                    </td>
                                    <td style="font-weight: 700; color: #0f172a; font-family: monospace;">
                                        R$ {{ number_format($os->total_amount, 2, ',', '.') }}
                                    </td>
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
            @endif

            @if($orders->hasPages())
                <div style="border-top: 1px solid #e2e8f0; padding: 8px 16px;">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
