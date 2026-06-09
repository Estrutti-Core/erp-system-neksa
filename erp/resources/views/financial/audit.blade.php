@extends('layouts.app')

@section('title', 'Auditoria Financeira')

@section('content')
<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('financial.audit') }}" class="flex flex-col gap-4 md:flex-row md:items-end mb-6">
            <div class="flex-1">
                <label for="user_id" class="form-label">Usuário</label>
                <select name="user_id" id="user_id" class="form-control">
                    <option value="">Todos</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:180px">
                <label for="event_type" class="form-label">Tipo de Evento</label>
                <select name="event_type" id="event_type" class="form-control">
                    <option value="">Todos</option>
                    <option value="created" {{ request('event_type') === 'created' ? 'selected' : '' }}>Criação</option>
                    <option value="full_payment" {{ request('event_type') === 'full_payment' ? 'selected' : '' }}>Quitação Completa</option>
                    <option value="partial_payment" {{ request('event_type') === 'partial_payment' ? 'selected' : '' }}>Baixa Parcial</option>
                    <option value="cancelled" {{ request('event_type') === 'cancelled' ? 'selected' : '' }}>Cancelamento</option>
                </select>
            </div>
            <div style="min-width:150px">
                <label for="start_date" class="form-label">Data Início</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ request('start_date') }}">
            </div>
            <div style="min-width:150px">
                <label for="end_date" class="form-label">Data Fim</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ request('end_date') }}">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><x-heroicon-o-funnel class="w-5 h-5"/> Filtrar</button>
                <a href="{{ route('financial.audit') }}" class="btn btn-secondary"><x-heroicon-o-x-mark class="w-5 h-5"/> Limpar</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 150px">Data/Hora</th>
                        <th>Entidade</th>
                        <th>ID da Entidade</th>
                        <th>Evento</th>
                        <th>Usuário</th>
                        <th>Novos Dados (JSON)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $event)
                        <tr>
                            <td>{{ $event->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>
                                <span class="font-mono text-xs text-gray-600 bg-gray-100 px-1 py-0.5 rounded">
                                    {{ class_basename($event->entity_type) }}
                                </span>
                            </td>
                            <td>{{ $event->entity_id }}</td>
                            <td>
                                @php
                                    $badge = match($event->event_type) {
                                        'created' => 'bg-green-100 text-green-800',
                                        'full_payment' => 'bg-blue-100 text-blue-800',
                                        'partial_payment' => 'bg-indigo-100 text-indigo-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                @endphp
                                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $badge }}">
                                    {{ $event->event_type }}
                                </span>
                            </td>
                            <td>{{ $event->user->name ?? 'Sistema / Automático' }}</td>
                            <td>
                                <details class="text-xs text-gray-500 cursor-pointer">
                                    <summary class="text-indigo-600 hover:text-indigo-800">Visualizar payload</summary>
                                    <pre class="bg-gray-50 p-2 rounded border border-gray-200 mt-2 overflow-x-auto" style="max-height: 200px"><code>{{ json_encode($event->new_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:32px; color:var(--text-secondary)">
                                Nenhum log de auditoria encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $events->links() }}
        </div>
    </div>
</div>
@endsection
