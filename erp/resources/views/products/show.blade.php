@extends('layouts.app')

@section('title', 'Detalhes do Item')

@section('content')
<div style="max-width: 900px; margin: 0 auto; padding-bottom: 40px;">
    <!-- Cabeçalho de Ações -->
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('products.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>
        
        @can('update', $product)
            <a href="{{ route('products.edit', $product) }}" class="btn btn-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);">
                <x-heroicon-o-pencil class="w-4 h-4"/> Editar Cadastro
            </a>
        @endcan
    </div>

    <!-- Layout Grid -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <!-- Card de Informações Principais -->
        <div>
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
                <div class="flex items-center gap-3 mb-4">
                    <div style="width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px;
                        background: {{ $product->isService() ? '#ede9fe' : '#e0f2fe' }}; 
                        color: {{ $product->isService() ? '#6d28d9' : '#0369a1' }};">
                        {{ $product->isService() ? 'S' : 'P' }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-{{ $product->type->color() }}">{{ $product->type->label() }}</span>
                            <span class="badge badge-{{ $product->is_active ? 'green' : 'red' }}">{{ $product->is_active ? 'Ativo' : 'Inativo' }}</span>
                        </div>
                        <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 4px;">{{ $product->name }}</h2>
                    </div>
                </div>

                <div style="border-top: 1px solid #f1f5f9; padding-top: 16px;">
                    <h4 style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Descrição</h4>
                    <p style="font-size: 14px; color: #334155; line-height: 1.6;">{!! nl2br(e($product->description ?: 'Sem descrição comercial fornecida.')) !!}</p>
                </div>
            </div>

            <!-- Dados Fiscais -->
            <div class="card shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 16px;">Estrutura Fiscal & Tributária</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 16px;">
                    <div>
                        <div style="font-size: 12px; color: #64748b;">NCM (Nomenclatura do Mercosul)</div>
                        <div style="font-family: monospace; font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ $product->ncm ?: 'Não informado' }}</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b;">CFOP (Código de Operação)</div>
                        <div style="font-family: monospace; font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ $product->cfop ?: 'Não informado' }}</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 16px;">
                    <div>
                        <div style="font-size: 12px; color: #64748b;">CST (Regime Normal)</div>
                        <div style="font-family: monospace; font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ $product->cst ?: 'Não informado' }}</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b;">CSOSN (Simples Nacional)</div>
                        <div style="font-family: monospace; font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ $product->csosn ?: 'Não informado' }}</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 16px;">
                    <div>
                        <div style="font-size: 12px; color: #64748b;">Unidade Comercial</div>
                        <div style="font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ $product->commercial_unit }}</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b;">Unidade Tributável</div>
                        <div style="font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ $product->taxable_unit }}</div>
                    </div>
                </div>

                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Origem do Produto/Serviço</div>
                    <div style="font-size: 13px; font-weight: 600; color: #1e293b;">
                        {{ $product->fiscal_origin->label() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar do Item -->
        <div>
            <!-- Valores Comerciais -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; background: linear-gradient(to bottom, #ffffff, #f8fafc);">
                <h3 style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px;">Financeiro</h3>
                
                <div class="mb-3">
                    <div style="font-size: 11px; color: #64748b;">PREÇO DE VENDA</div>
                    <div style="font-size: 24px; font-weight: 800; color: #4f46e5; margin-top: 2px;">R$ {{ number_format($product->sale_price, 2, ',', '.') }}</div>
                </div>

                <div class="mb-3" style="border-top: 1px solid #f1f5f9; padding-top: 12px;">
                    <div style="font-size: 11px; color: #64748b;">PREÇO DE CUSTO</div>
                    <div style="font-size: 15px; font-weight: 600; color: #475569; margin-top: 2px;">R$ {{ number_format($product->cost_price, 2, ',', '.') }}</div>
                </div>

                @if($product->cost_price > 0)
                    <div class="mb-2" style="background: #ecfdf5; border-radius: 6px; padding: 8px; display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: #065f46; font-weight: 600;">
                        <x-heroicon-o-arrow-trending-up class="w-4 h-4"/> 
                        Margem: {{ number_format((($product->sale_price - $product->cost_price) / $product->cost_price) * 100, 1) }}%
                    </div>
                @endif
            </div>

            <!-- Dados Operacionais -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px;">
                <h3 style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Dados Operacionais</h3>
                
                <div class="mb-3">
                    <div style="font-size: 11px; color: #64748b;">SKU</div>
                    <div style="font-family: monospace; font-size: 13px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ $product->sku }}</div>
                </div>

                <div class="mb-3">
                    <div style="font-size: 11px; color: #64748b;">Código de Barras</div>
                    <div style="font-family: monospace; font-size: 13px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ $product->barcode ?: '—' }}</div>
                </div>

                <div class="mb-1">
                    <div style="font-size: 11px; color: #64748b;">Controle de Estoque</div>
                    <div style="font-size: 13px; font-weight: 600; color: #1e293b; margin-top: 2px;">
                        @if($product->isService())
                            <span class="badge badge-slate">Serviço</span>
                        @elseif($product->is_stock_controlled)
                            <span class="badge badge-green">Controlado</span>
                            <div style="font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 8px;">
                                {{ number_format($product->stock ?? 0, 0, ',', '.') }} {{ $product->commercial_unit }}
                            </div>
                        @else
                            <span class="badge badge-slate">Não controlado</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Notas Internas -->
            @if($product->internal_notes)
                <div class="card shadow-sm" style="border-radius: 12px; border: 1px solid #fee2e2; padding: 20px; background: #fff5f5;">
                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; color: #dc2626; font-weight: 700; font-size: 13px;">
                        <x-heroicon-o-exclamation-triangle class="w-4 h-4"/> Observações Internas
                    </div>
                    <p style="font-size: 12px; color: #7f1d1d; line-height: 1.5;">{{ $product->internal_notes }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
