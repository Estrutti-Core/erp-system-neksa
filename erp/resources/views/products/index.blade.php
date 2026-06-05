@extends('layouts.app')

@section('title', 'Produtos & Serviços')

@section('topbar-actions')
    @can('create', App\Models\Product::class)
        <a href="{{ route('products.create') }}" class="btn btn-primary shadow-sm"
            style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);">
            <x-heroicon-o-plus class="w-4 h-4" /> Novo Item
        </a>
    @endcan
@endsection

@section('content')
    <div style="max-width: 1200px; margin: 0 auto;">
        <!-- Filtros -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; padding: 16px;">
            <form method="GET" action="{{ route('products.index') }}"
                class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3 flex-wrap" style="flex: 1;">
                    <div style="position: relative; min-width: 280px; flex: 1;">
                        <span
                            style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;">
                            <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                        </span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                            placeholder="Buscar por nome, SKU ou código..." style="padding-left: 36px; border-radius: 8px;">
                    </div>

                    <div>
                        <select name="type" class="form-control" style="border-radius: 8px; min-width: 140px;"
                            onchange="this.form.submit()">
                            <option value="">Todos os tipos</option>
                            <option value="product" {{ request('type') === 'product' ? 'selected' : '' }}>Produtos</option>
                            <option value="service" {{ request('type') === 'service' ? 'selected' : '' }}>Serviços</option>
                        </select>
                    </div>

                    <div>
                        <select name="status" class="form-control" style="border-radius: 8px; min-width: 140px;"
                            onchange="this.form.submit()">
                            <option value="active" {{ request('status') === 'active' || !request('status') ? 'selected' : '' }}>Ativos</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inativos
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm" style="border-radius: 8px;">Filtrar</button>
                    @if(request()->anyFilled(['search', 'type', 'status']))
                        <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm"
                            style="border-radius: 8px; color: #ef4444;">Limpar</a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Tabela de Itens -->
        <div class="card shadow-sm" style="border-radius: 12px; padding: 0; overflow: hidden; border: 1px solid #e2e8f0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="font-weight: 600; padding: 14px 20px;">Item</th>
                            <th>SKU</th>
                            <th>Tipo</th>
                            <th>Preço Venda</th>
                            <th>Estoque</th>
                            <th>Fiscal</th>
                            <th>Status</th>
                            <th style="text-align: right; padding-right: 20px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td style="padding: 14px 20px;">
                                    <div class="flex items-center gap-3">
                                        <div style="width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; 
                                                background: {{ $product->isService() ? '#ede9fe' : '#e0f2fe' }}; 
                                                color: {{ $product->isService() ? '#6d28d9' : '#0369a1' }};">
                                            {{ $product->isService() ? 'S' : 'P' }}
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #1e293b; font-size: 14px;">{{ $product->name }}
                                            </div>
                                            <div style="font-size: 12px; color: #64748b;" class="truncate">
                                                {{ $product->description ?: 'Sem descrição' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-family: monospace; font-size: 13px; color: #334155;">{{ $product->sku }}</td>
                                <td>
                                    <span class="badge badge-{{ $product->type->color() }}">
                                        {{ $product->type->label() }}
                                    </span>
                                </td>
                                <td style="font-weight: 600; color: #0f172a;">R$
                                    {{ number_format($product->sale_price, 2, ',', '.') }}</td>
                                <td>
                                    @if($product->isService())
                                        <span style="color: #94a3b8;">—</span>
                                    @elseif($product->is_stock_controlled)
                                        <span
                                            style="font-weight: 500; color: {{ ($product->stock ?? 0) <= 0 ? '#ef4444' : '#10b981' }}">
                                            {{ number_format($product->stock ?? 0, 0, ',', '.') }} {{ $product->commercial_unit }}
                                        </span>
                                    @else
                                        <span class="badge badge-slate" style="font-size: 11px;">Sem controle</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="font-size: 12px; color: #64748b;">
                                        <div>NCM: <span
                                                style="font-family: monospace; font-weight: 500;">{{ $product->ncm ?: '—' }}</span>
                                        </div>
                                        <div>CFOP: <span
                                                style="font-family: monospace; font-weight: 500;">{{ $product->cfop ?: '—' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $product->is_active ? 'green' : 'red' }}">
                                        {{ $product->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td style="text-align: right; padding-right: 20px;">
                                    <div class="flex gap-2 justify-end">
                                        <a href="{{ route('products.show', $product) }}" class="btn btn-secondary btn-sm"
                                            style="padding: 6px; border-radius: 6px;" title="Ver detalhes">
                                            <x-heroicon-o-eye class="w-4 h-4" />
                                        </a>
                                        @can('update', $product)
                                            <a href="{{ route('products.edit', $product) }}" class="btn btn-secondary btn-sm"
                                                style="padding: 6px; border-radius: 6px;" title="Editar">
                                                <x-heroicon-o-pencil class="w-4 h-4" />
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px 20px; color: #64748b;">
                                    <x-heroicon-o-cube class="w-10 h-10" style="margin: 0 auto 12px; color: #cbd5e1;" />
                                    <p style="font-weight: 500;">Nenhum produto ou serviço encontrado</p>
                                    <p style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Tente alterar os filtros ou
                                        cadastrar um novo item.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($products->hasPages())
                <div style="border-top: 1px solid #e2e8f0; padding: 8px 16px;">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection