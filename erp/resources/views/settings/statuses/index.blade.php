@extends('layouts.app')
@section('title', 'Status de Ordens de Serviço')

@section('topbar-actions')
    <a href="{{ route('settings.statuses.create') }}" class="btn btn-primary btn-sm">+ Novo Status</a>
@endsection

@section('content')
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Cor</th>
                    <th>Tipo de Estado</th>
                    <th>SLA de Permanência</th>
                    <th>Tempo Esperado</th>
                    <th>Transições Permitidas</th>
                    <th style="text-align: right; padding-right: 20px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($statuses as $s)
                <tr>
                    <td>
                        <span class="badge badge-{{ $s->color }} font-semibold" style="font-size:12px; padding: 4px 10px;">
                            {{ $s->name }}
                        </span>
                        @if($s->is_system)
                            <span class="text-xs text-muted block mt-1" style="font-style: italic;">Sistema</span>
                        @endif
                    </td>
                    <td>
                        <span style="font-family: monospace; font-size:12px; color:#475569">{{ $s->color }}</span>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @if($s->is_open_state)
                                <span class="badge badge-blue text-xs">Aberto</span>
                            @endif
                            @if($s->is_completed_state)
                                <span class="badge badge-green text-xs">Conclusão</span>
                            @endif
                            @if($s->is_cancelled_state)
                                <span class="badge badge-red text-xs">Cancelamento</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @if($s->max_stay_minutes)
                            <span class="font-medium text-sm">{{ $s->max_stay_minutes }} min</span>
                        @else
                            <span class="text-muted text-sm">—</span>
                        @endif
                    </td>
                    <td>
                        @if($s->expected_time_minutes)
                            <span class="font-medium text-sm">{{ $s->expected_time_minutes }} min</span>
                        @else
                            <span class="text-muted text-sm">—</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1" style="max-width: 250px;">
                            @forelse($s->allowedTransitions as $t)
                                <span class="badge badge-slate text-xs" style="font-weight: 500; font-size: 11px;">
                                    {{ $t->name }}
                                </span>
                            @empty
                                <span class="text-xs text-muted" style="font-style: italic;">Nenhuma transição direta</span>
                            @endforelse
                        </div>
                    </td>
                    <td style="text-align: right; padding-right: 20px;">
                        <div class="flex gap-2 justify-end">
                            <a href="{{ route('settings.statuses.edit', $s) }}" class="btn btn-secondary btn-sm"
                                style="padding: 6px; border-radius: 6px;" title="Editar">
                                <x-heroicon-o-pencil class="w-4 h-4" />
                            </a>
                            @if(!$s->is_system && !$s->is_completed_state && !$s->is_cancelled_state)
                                <form action="{{ route('settings.statuses.destroy', $s) }}" method="POST" style="display:inline;" onsubmit="return confirm('Deseja realmente remover este status?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-secondary btn-sm" style="padding: 6px; border-radius: 6px; color: #dc2626;" title="Excluir">
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
