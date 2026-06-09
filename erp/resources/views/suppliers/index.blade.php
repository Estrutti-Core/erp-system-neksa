@extends('layouts.app')
@section('title', 'Fornecedores')
@section('topbar-actions')
    @can('create', App\Models\Supplier::class)
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary btn-sm">+ Novo Fornecedor</a>
    @endcan
@endsection

@section('content')
<div class="card mb-4">
    <form method="GET" class="flex gap-3 flex-wrap items-center">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Nome, CNPJ, e-mail..." class="form-control" style="max-width:280px">
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        @if(request()->has('search'))
            <a href="{{ route('suppliers.index') }}" class="btn btn-secondary btn-sm">Limpar</a>
        @endif
    </form>
</div>

<div class="card">
    @if($suppliers->isEmpty())
        <div style="text-align:center;padding:48px;color:#94a3b8">
            <div class="flex justify-center" style="margin-bottom:12px"><x-heroicon-o-truck class="w-10 h-10 text-gray-300"/></div>
            <div style="font-size:16px;font-weight:600;margin-bottom:6px">Nenhum fornecedor encontrado</div>
            <a href="{{ route('suppliers.create') }}" class="btn btn-primary mt-3">+ Cadastrar Fornecedor</a>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome / Razão Social</th>
                        <th>Documento</th>
                        <th>Telefone</th>
                        <th>Pedidos de Compra</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suppliers as $supplier)
                    <tr>
                        <td>
                            <div class="font-semibold">{{ $supplier->name }}</div>
                            <div class="text-xs text-muted">{{ $supplier->email ?? '—' }}</div>
                        </td>
                        <td class="text-sm">
                            @if($supplier->document)
                                <span class="badge badge-indigo text-xs" style="text-transform: uppercase;">{{ $supplier->document_type }}</span>
                                <span class="text-sm font-mono" style="margin-left:4px">{{ $supplier->document }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-sm">{{ $supplier->phone ?? '—' }}</td>
                        <td>
                            <span style="font-weight:700;color:var(--primary)">{{ $supplier->purchase_orders_count }}</span>
                        </td>
                        <td>
                            <div class="flex gap-2">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-secondary btn-sm">Ver</a>
                                @can('update', $supplier)
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-secondary btn-sm">Editar</a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $suppliers->links() }}</div>
    @endif
</div>
@endsection
