@extends('layouts.app')
@section('title', 'Condições de Pagamento')
@section('topbar-actions')
    <a href="{{ route('payment-conditions.create') }}" class="btn btn-primary btn-sm">+ Nova Condição</a>
@endsection

@section('content')
<div class="card">
    @if($conditions->isEmpty())
        <div style="text-align:center;padding:48px;color:#94a3b8">
            <div class="flex justify-center" style="margin-bottom:12px">
                <x-heroicon-o-credit-card class="w-10 h-10 text-gray-300"/>
            </div>
            <div style="font-size:16px;font-weight:600;margin-bottom:6px">Nenhuma condição de pagamento cadastrada</div>
            <a href="{{ route('payment-conditions.create') }}" class="btn btn-primary mt-3">+ Cadastrar Condição</a>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Parcelas</th>
                        <th>Intervalo</th>
                        <th>Tipo Padrão</th>
                        <th>Conta Padrão</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($conditions as $cond)
                    <tr>
                        <td>
                            <div class="font-semibold">{{ $cond->name }}</div>
                        </td>
                        <td>
                            @if($cond->type === 'cash')
                                <span class="badge badge-success text-xs">À Vista</span>
                            @elseif($cond->type === 'installments')
                                <span class="badge badge-primary text-xs">Parcelado</span>
                            @else
                                <span class="badge badge-secondary text-xs">Personalizado</span>
                            @endif
                        </td>
                        <td>{{ $cond->installments_count }}x</td>
                        <td>{{ $cond->interval_days }} dias</td>
                        <td>
                            @if($cond->default_payment_method)
                                <span class="badge badge-slate text-xs">
                                    {{ ['pix' => 'Pix', 'credit_card' => 'Cartão de Crédito', 'debit_card' => 'Cartão de Débito', 'cash' => 'Dinheiro', 'bank_transfer' => 'Transferência Bancária'][$cond->default_payment_method] ?? $cond->default_payment_method }}
                                </span>
                            @else
                                <span class="text-sm text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            {{ $cond->defaultFinancialAccount?->name ?? '—' }}
                        </td>
                        <td>
                            @if($cond->is_active)
                                <span class="badge badge-success text-xs">Ativa</span>
                            @else
                                <span class="badge badge-danger text-xs">Inativa</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex gap-2">
                                <a href="{{ route('payment-conditions.edit', $cond) }}" class="btn btn-secondary btn-sm">Editar</a>
                                <form action="{{ route('payment-conditions.destroy', $cond) }}" method="POST" onsubmit="return confirm('Deseja realmente excluir esta condição de pagamento?')">
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
    @endif
</div>
@endsection
