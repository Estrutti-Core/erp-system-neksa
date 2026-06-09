@extends('layouts.app')
@section('title', 'Contas Financeiras')
@section('topbar-actions')
    <a href="{{ route('financial-accounts.create') }}" class="btn btn-primary btn-sm">+ Nova Conta</a>
@endsection

@section('content')
<div class="card mb-4">
    <form method="GET" class="flex gap-3 flex-wrap items-center">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Nome, banco..." class="form-control" style="max-width:280px">
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        @if(request()->has('search'))
            <a href="{{ route('financial-accounts.index') }}" class="btn btn-secondary btn-sm">Limpar</a>
        @endif
    </form>
</div>

<div class="card">
    @if($accounts->isEmpty())
        <div style="text-align:center;padding:48px;color:#94a3b8">
            <div class="flex justify-center" style="margin-bottom:12px"><x-heroicon-o-banknotes class="w-10 h-10 text-gray-300"/></div>
            <div style="font-size:16px;font-weight:600;margin-bottom:6px">Nenhuma conta financeira cadastrada</div>
            <a href="{{ route('financial-accounts.create') }}" class="btn btn-primary mt-3">+ Cadastrar Conta</a>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Banco</th>
                        <th>Agência / Conta</th>
                        <th>Saldo Atual</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                    <tr>
                        <td>
                            <div class="font-semibold">{{ $account->name }}</div>
                        </td>
                        <td>
                            <span class="badge badge-secondary text-xs">{{ $account->type->name ?? '—' }}</span>
                        </td>
                        <td>{{ $account->bank_name ?? '—' }}</td>
                        <td>
                            @if($account->agency || $account->account_number)
                                <span class="text-sm font-mono">{{ $account->agency ?? '—' }} / {{ $account->account_number ?? '—' }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="font-bold text-sm @if($account->balance >= 0) text-success @else text-danger @endif">
                                R$ {{ number_format($account->balance, 2, ',', '.') }}
                            </span>
                        </td>
                        <td>
                            @if($account->is_active)
                                <span class="badge badge-success text-xs">Ativa</span>
                            @else
                                <span class="badge badge-danger text-xs">Inativa</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex gap-2">
                                <a href="{{ route('financial-accounts.edit', $account) }}" class="btn btn-secondary btn-sm">Editar</a>
                                <form action="{{ route('financial-accounts.destroy', $account) }}" method="POST" onsubmit="return confirm('Deseja realmente excluir esta conta financeira?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" style="background-color:#ef4444;color:white;border:none">Excluir</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $accounts->links() }}</div>
    @endif
</div>
@endsection
