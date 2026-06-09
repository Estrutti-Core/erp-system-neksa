@extends('layouts.app')

@section('title', 'Painel e Fluxo de Caixa')

@section('content')
<div class="space-y-6">
    <!-- Consolidação de Contas Financeiras -->
    <div class="card mb-6">
        <div class="card-header flex justify-between items-center" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <div>
                <h2 class="card-title text-lg font-bold" style="margin: 0">Saldos de Contas e Disponibilidades</h2>
                <p class="text-xs text-muted">Posição consolidada em tempo real de todas as contas ativas.</p>
            </div>
            <div>
                <a href="{{ route('financial-accounts.index') }}" class="btn btn-secondary btn-sm">
                    Gerenciar Contas
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div class="p-4 rounded border" style="background-color: #f8fafc; border-color: #e2e8f0;">
                <span class="text-xs text-muted block">Disponível Consolidado</span>
                <div class="text-xl font-black @if($consolidated_balance >= 0) text-success @else text-danger @endif mt-1">
                    R$ {{ number_format($consolidated_balance, 2, ',', '.') }}
                </div>
            </div>

            @foreach($accounts->take(3) as $acc)
                <div class="p-4 rounded border" style="background-color: white; border-color: #e2e8f0;">
                    <span class="text-xs text-muted block">{{ $acc->name }}</span>
                    <div class="text-md font-bold mt-1 @if($acc->balance >= 0) text-success @else text-danger @endif">
                        R$ {{ number_format($acc->balance, 2, ',', '.') }}
                    </div>
                </div>
            @endforeach

            @if($accounts->count() > 3)
                <div class="p-4 rounded border flex items-center justify-center" style="background-color: white; border-color: #e2e8f0;">
                    <a href="{{ route('financial-accounts.index') }}" class="text-xs font-semibold text-primary">
                        + {{ $accounts->count() - 3 }} outras contas
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Filtros de Fluxo de Caixa -->
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('financial.cash-flow') }}" class="flex flex-col gap-4 md:flex-row md:items-end">
                <div class="flex-1">
                    <label for="regime" class="form-label">Regime de Apuração</label>
                    <select name="regime" id="regime" class="form-control">
                        <option value="caixa" {{ $regime === 'caixa' ? 'selected' : '' }}>Regime de Caixa (Data de Pagamento)</option>
                        <option value="competencia" {{ $regime === 'competencia' ? 'selected' : '' }}>Regime de Competência (Data de Vencimento/Faturamento)</option>
                    </select>
                </div>
                <div style="min-width:200px">
                    <label for="start_date" class="form-label">Data Início</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $startDate }}">
                </div>
                <div style="min-width:200px">
                    <label for="end_date" class="form-label">Data Fim</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $endDate }}">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="{{ route('financial.cash-flow') }}" class="btn btn-secondary">Resetar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Cards de Totais -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="card bg-green-50 border border-green-200" style="background-color: #f0fdf4; border-color: #bbf7d0;">
            <div class="card-body">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-green-800">Total de Entradas</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-bold text-green-900">R$ {{ number_format($total_inputs, 2, ',', '.') }}</h3>
                    <p class="text-xs text-green-700 mt-1">Soma de recebimentos no período</p>
                </div>
            </div>
        </div>

        <div class="card bg-red-50 border border-red-200" style="background-color: #fef2f2; border-color: #fecaca;">
            <div class="card-body">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-red-800">Total de Saídas</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-bold text-red-900">R$ {{ number_format($total_outputs, 2, ',', '.') }}</h3>
                    <p class="text-xs text-red-700 mt-1">Soma de pagamentos no período</p>
                </div>
            </div>
        </div>

        <div class="card {{ $net_balance >= 0 ? 'bg-indigo-50 border-indigo-200' : 'bg-rose-50 border-rose-200' }}" style="@if($net_balance >= 0) background-color: #e0e7ff; border-color: #c7d2fe; @else background-color: #fff1f2; border-color: #fecdd3; @endif">
            <div class="card-body">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium @if($net_balance >= 0) text-indigo-800 @else text-rose-800 @endif">Saldo Líquido</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-bold @if($net_balance >= 0) text-indigo-900 @else text-rose-900 @endif">
                        R$ {{ number_format($net_balance, 2, ',', '.') }}
                    </h3>
                    <p class="text-xs @if($net_balance >= 0) text-indigo-700 @else text-rose-700 @endif mt-1">Resultado financeiro consolidado</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela Detalhada Dia a Dia -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title text-md font-bold">Detalhamento Diário</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Entradas (Receitas)</th>
                            <th>Saídas (Despesas)</th>
                            <th>Saldo do Dia</th>
                            <th style="width: 150px; text-align: right">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($timeline as $date => $values)
                            <tr>
                                <td><strong>{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</strong></td>
                                <td class="text-green-700 font-medium">R$ {{ number_format($values['inputs'], 2, ',', '.') }}</td>
                                <td class="text-red-700 font-medium">R$ {{ number_format($values['outputs'], 2, ',', '.') }}</td>
                                <td class="font-semibold @if($values['balance'] >= 0) text-indigo-700 @else text-rose-700 @endif">
                                    R$ {{ number_format($values['balance'], 2, ',', '.') }}
                                </td>
                                <td style="text-align: right">
                                    @if($values['balance'] > 0)
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800" style="background-color: #d1fae5; color: #065f46;">Superavitário</span>
                                    @elseif($values['balance'] < 0)
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800" style="background-color: #fee2e2; color: #991b1b;">Deficitário</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800" style="background-color: #f1f5f9; color: #475569;">Neutro</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align:center; padding:32px; color:var(--text-secondary)">
                                    Nenhum dado encontrado para o período selecionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
