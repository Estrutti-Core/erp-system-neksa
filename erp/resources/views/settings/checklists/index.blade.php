@extends('layouts.app')
@section('title', 'Templates de Checklist')

@section('topbar-actions')
    <a href="{{ route('settings.checklists.create') }}" class="btn btn-primary btn-sm">+ Novo Checklist</a>
@endsection

@section('content')
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Descrição</th>
                    <th>Qtd. Perguntas</th>
                    <th style="text-align: right; padding-right: 20px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $t)
                <tr>
                    <td>
                        <span class="font-semibold" style="font-size:14px; color:var(--dark)">
                            {{ $t->name }}
                        </span>
                    </td>
                    <td>
                        <span style="font-size:13px; color:#475569">{{ $t->description ?? '—' }}</span>
                    </td>
                    <td>
                        <span class="badge badge-slate text-xs font-semibold">{{ $t->questions_count }}</span>
                    </td>
                    <td style="text-align: right; padding-right: 20px;">
                        <div class="flex gap-2 justify-end">
                            <a href="{{ route('settings.checklists.edit', $t) }}" class="btn btn-secondary btn-sm"
                                style="padding: 6px; border-radius: 6px;" title="Editar">
                                <x-heroicon-o-pencil class="w-4 h-4" />
                            </a>
                            <form action="{{ route('settings.checklists.destroy', $t) }}" method="POST" style="display:inline;" onsubmit="return confirm('Deseja realmente remover este template?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-secondary btn-sm" style="padding: 6px; border-radius: 6px; color: #dc2626;" title="Excluir">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted" style="padding: 30px;">
                        Nenhum template de checklist cadastrado ainda.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
