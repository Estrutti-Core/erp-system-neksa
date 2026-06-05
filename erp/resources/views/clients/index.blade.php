@extends('layouts.app')
@section('title', 'Clientes')
@section('topbar-actions')
    @can('create', App\Models\Client::class)
        <a href="{{ route('clients.create') }}" class="btn btn-primary btn-sm">+ Novo Cliente</a>
    @endcan
@endsection

@section('content')
<div class="card mb-4">
    <form method="GET" class="flex gap-3 flex-wrap items-center">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Nome, CPF/CNPJ, e-mail..." class="form-control" style="max-width:280px">
        <select name="status" class="form-control" style="max-width:160px">
            <option value="">Todos</option>
            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Ativos</option>
            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inativos</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        @if(request()->hasAny(['search','status']))
            <a href="{{ route('clients.index') }}" class="btn btn-secondary btn-sm">Limpar</a>
        @endif
    </form>
</div>

<div class="card">
    @if($clients->isEmpty())
        <div style="text-align:center;padding:48px;color:#94a3b8">
            <div class="flex justify-center" style="margin-bottom:12px"><x-heroicon-o-users class="w-10 h-10 text-gray-300"/></div>
            <div style="font-size:16px;font-weight:600;margin-bottom:6px">Nenhum cliente encontrado</div>
            <a href="{{ route('clients.create') }}" class="btn btn-primary mt-3">+ Cadastrar Cliente</a>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Documento</th>
                        <th>Telefone</th>
                        <th>Cidade</th>
                        <th>OS</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clients as $client)
                    <tr>
                        <td>
                            <div class="font-semibold">{{ $client->name }}</div>
                            <div class="text-xs text-muted">{{ $client->email ?? '—' }}</div>
                        </td>
                        <td class="text-sm">{{ $client->formatted_document ?: '—' }}</td>
                        <td class="text-sm">{{ $client->phone ?? '—' }}</td>
                        <td class="text-sm">{{ $client->primaryAddress?->city ?? '—' }}/{{ $client->primaryAddress?->state ?? '' }}</td>
                        <td>
                            <span style="font-weight:700;color:var(--primary)">{{ $client->service_orders_count }}</span>
                        </td>
                        <td>
                            @if($client->is_active)
                                <span class="badge badge-green">Ativo</span>
                            @else
                                <span class="badge badge-slate">Inativo</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex gap-2">
                                <a href="{{ route('clients.show', $client) }}" class="btn btn-secondary btn-sm">Ver</a>
                                @can('update', $client)
                                    <a href="{{ route('clients.edit', $client) }}" class="btn btn-secondary btn-sm">Editar</a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $clients->links() }}</div>
    @endif
</div>
@endsection
