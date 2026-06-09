@extends('layouts.app')
@section('title', $supplier->name)

@section('content')
<div style="max-width: 900px; margin: 0 auto; padding-bottom: 40px;">
    <!-- Cabeçalho -->
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <a href="{{ route('suppliers.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>
        <div class="flex gap-2">
            @can('update', $supplier)
                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary btn-sm">Editar Fornecedor</a>
            @endcan
            @can('delete', $supplier)
                <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este fornecedor?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary btn-sm" style="color: #ef4444; border-color: #fee2e2;">Excluir</button>
                </form>
            @endcan
        </div>
    </div>

    <!-- Card de Informações Básicas -->
    <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
        <h3 style="font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
            Dados do Fornecedor
        </h3>
        
        <div class="grid-2">
            <div>
                <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Nome / Razão Social</span>
                <p style="font-size: 15px; font-weight: 600; color: #1e293b; margin: 2px 0 12px 0;">{{ $supplier->name }}</p>
                
                <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Documento (CNPJ/CPF)</span>
                <p style="font-size: 14px; color: #334155; margin: 2px 0 12px 0; font-family: monospace;">
                    @if($supplier->document)
                        <span class="badge badge-indigo text-xs" style="text-transform: uppercase; font-family: sans-serif; margin-right:4px;">{{ $supplier->document_type }}</span>
                        {{ $supplier->document }}
                    @else
                        Não cadastrado
                    @endif
                </p>
            </div>
            <div>
                <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Telefone</span>
                <p style="font-size: 14px; color: #334155; margin: 2px 0 12px 0;">{{ $supplier->phone ?: 'Não informado' }}</p>
                
                <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">E-mail</span>
                <p style="font-size: 14px; color: #334155; margin: 2px 0 12px 0;">{{ $supplier->email ?: 'Não informado' }}</p>
            </div>
        </div>
    </div>

    <!-- Histórico de Pedidos de Compra -->
    <div class="card shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
        <h3 style="font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
            Pedidos de Compra Recentes
        </h3>

        @if($supplier->purchaseOrders->isEmpty())
            <div style="text-align: center; padding: 24px; color: #94a3b8;">
                Nenhum pedido de compra registrado para este fornecedor.
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th style="text-align: right;">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($supplier->purchaseOrders as $order)
                        <tr>
                            <td class="font-mono text-sm">{{ $order->code }}</td>
                            <td class="text-sm">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="badge badge-{{ $order->status->color() }}">{{ $order->status->label() }}</span>
                            </td>
                            <td class="text-sm font-semibold" style="text-align: right;">
                                R$ {{ number_format($order->total_amount, 2, ',', '.') }}
                            </td>
                            <td>
                                <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-secondary btn-sm">Visualizar</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
