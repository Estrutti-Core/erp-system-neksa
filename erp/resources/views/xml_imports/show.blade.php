@extends('layouts.app')
@section('title', 'Detalhes da Importação XML')

@section('content')
<div class="card mb-4">
    <div class="flex justify-between items-center flex-wrap gap-4 pb-4 border-b">
        <div>
            <h2 class="text-lg font-bold">Resumo da Nota Fiscal</h2>
            <div class="text-sm text-muted font-mono mt-1">Chave: {{ $xmlImport->access_key }}</div>
            <div class="text-sm mt-1">
                Fornecedor Emitente: 
                <strong>
                    @if($xmlImport->supplier)
                        {{ $xmlImport->supplier->name }} (CNPJ: {{ $xmlImport->supplier->cnpj }})
                    @else
                        {{ $xmlImport->items->first()?->supplier_product_name ?? 'Não cadastrado' }}
                    @endif
                </strong>
            </div>
        </div>
        <div class="text-right">
            <span class="text-sm text-muted block">Valor Total da Nota</span>
            <div class="text-xl font-bold text-primary">R$ {{ number_format($xmlImport->total_amount, 2, ',', '.') }}</div>
            <div class="text-xs mt-1">
                Status: 
                @if($xmlImport->status === 'confirmed')
                    <span class="badge badge-success">Confirmado / Integrado</span>
                @else
                    <span class="badge badge-warning">Aguardando Mapeamento</span>
                @endif
            </div>
        </div>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger mb-4" style="background-color:#fee2e2;color:#991b1b;padding:12px;border-radius:6px;font-size:14px">
        {{ session('error') }}
    </div>
@endif

@if(session('success'))
    <div class="alert alert-success mb-4" style="background-color:#d1fae5;color:#065f46;padding:12px;border-radius:6px;font-size:14px">
        {{ session('success') }}
    </div>
@endif

<div class="card">
    <h3 class="text-md font-semibold mb-4">Itens Constantes no XML da NF-e</h3>
    <p class="text-xs text-muted mb-4">Mapeie cada SKU do fornecedor com o seu respectivo Produto no ERP para permitir a atualização de estoque e custo.</p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Cód. Fornecedor (SKU)</th>
                    <th>Descrição no XML</th>
                    <th>Qtd</th>
                    <th>Custo Unit.</th>
                    <th>Total</th>
                    <th>Produto Interno ERP</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                @foreach($xmlImport->items as $item)
                <tr>
                    <td class="font-mono text-sm">{{ $item->supplier_product_code }}</td>
                    <td>{{ $item->supplier_product_name }}</td>
                    <td>{{ number_format($item->quantity, 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($item->total_price, 2, ',', '.') }}</td>
                    <td>
                        @if($item->resolved && $item->product)
                            <div class="font-semibold text-sm">{{ $item->product->name }}</div>
                            <div class="text-xs text-muted">SKU Interno: {{ $item->product->sku ?? '—' }}</div>
                        @else
                            <span class="text-danger italic text-xs">Produto não associado</span>
                        @endif
                    </td>
                    <td>
                        @if($xmlImport->status !== 'confirmed')
                            <form action="{{ route('xml-imports.resolve-item', $item) }}" method="POST" class="flex gap-2">
                                @csrf
                                <select name="product_id" class="form-control text-xs" required style="max-width:200px">
                                    <option value="">Selecione para mapear...</option>
                                    @foreach($products as $prod)
                                        <option value="{{ $prod->id }}" {{ $item->product_id == $prod->id ? 'selected' : '' }}>
                                            {{ $prod->name }} (SKU: {{ $prod->sku ?? '—' }})
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px;">Mapear</button>
                            </form>
                        @else
                            <span class="text-muted text-xs">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex gap-3 mt-8 pt-4 border-t">
        @if($xmlImport->status !== 'confirmed')
            @php
                $unresolvedCount = $xmlImport->items()->where('resolved', false)->count();
            @endphp

            @if($unresolvedCount === 0)
                <form action="{{ route('xml-imports.confirm', $xmlImport) }}" method="POST" onsubmit="return confirm('Deseja realmente confirmar esta importação? Isso atualizará o estoque físico de todos os itens e gerará o contas a pagar.')">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                        Efetivar Entrada de Estoque e Contas a Pagar
                    </button>
                </form>
            @else
                <button class="btn btn-secondary" disabled title="Mapeie todos os produtos antes de confirmar." style="opacity: 0.6; cursor: not-allowed; padding: 10px 20px;">
                    Efetivar Entrada (Mapeamento Pendente: {{ $unresolvedCount }} itens)
                </button>
            @endif
        @endif

        <a href="{{ route('xml-imports.index') }}" class="btn btn-secondary" style="padding: 10px 20px;">
            Voltar
        </a>
    </div>
</div>
@endsection
