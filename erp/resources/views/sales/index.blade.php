@extends('layouts.app')

@section('title', 'Vendas')

@section('topbar-actions')
    <a href="{{ route('sales.export.xlsx', request()->all()) }}" class="btn btn-success flex items-center gap-2" style="background-color: #16a34a; border-color: #16a34a; color: white; border-radius: 8px;">
        <x-heroicon-o-document-arrow-down class="w-4 h-4"/> Exportar (XLSX)
    </a>
@endsection

@section('content')
    <div style="max-width: 1200px; margin: 0 auto;">
        <!-- Filtros -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; padding: 16px;">
            <form method="GET" action="{{ route('sales.index') }}"
                class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3 flex-wrap" style="flex: 1;">
                    <div style="position: relative; min-width: 280px; flex: 1;">
                        <span
                            style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;">
                            <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                        </span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                            placeholder="Buscar por código ou cliente..." style="padding-left: 36px; border-radius: 8px;">
                    </div>

                    <div>
                        <select name="status" class="form-control" style="border-radius: 8px; min-width: 160px;"
                            onchange="this.form.submit()">
                            <option value="">Todos os status</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm" style="border-radius: 8px;">Filtrar</button>
                    @if(request()->anyFilled(['search', 'status']))
                        <a href="{{ route('sales.index') }}" class="btn btn-secondary btn-sm"
                            style="border-radius: 8px; color: #ef4444;">Limpar</a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Tabela de Vendas -->
        <div class="card shadow-sm" style="border-radius: 12px; padding: 0; overflow: hidden; border: 1px solid #e2e8f0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="font-weight: 600; padding: 14px 20px;">Código</th>
                            <th>Cliente</th>
                            <th>Data Emissão</th>
                            <th>Valor Itens</th>
                            <th>Desconto</th>
                            <th>Valor Total</th>
                            <th>Status</th>
                            <th style="text-align: right; padding-right: 20px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                            <tr>
                                <td style="padding: 14px 20px; font-weight: 700; color: #10b981; font-family: monospace;">
                                    {{ $sale->code }}
                                </td>
                                <td>
                                    <div>
                                        <div style="font-weight: 600; color: #1e293b;">{{ $sale->client->name }}</div>
                                        <div style="font-size: 11px; color: #64748b;">CPF/CNPJ:
                                            {{ $sale->client->formatted_document }}</div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 500; color: #475569;">
                                        {{ $sale->created_at->format('d/m/Y H:i') }}
                                    </span>
                                </td>
                                <td style="font-family: monospace; color: #475569;">
                                    R$ {{ number_format($sale->items_amount, 2, ',', '.') }}
                                </td>
                                <td style="font-family: monospace; color: #ef4444;">
                                    @if($sale->discount_amount > 0)
                                        - R$ {{ number_format($sale->discount_amount, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td style="font-weight: 700; color: #0f172a; font-family: monospace;">
                                    R$ {{ number_format($sale->total_amount, 2, ',', '.') }}
                                </td>
                                <td>
                                    <span class="badge badge-{{ $sale->status->color() }}">
                                        {{ $sale->status->label() }}
                                    </span>
                                </td>
                                <td style="text-align: right; padding-right: 20px;">
                                    <div class="flex gap-2 justify-end">
                                        <a href="{{ route('sales.show', $sale) }}" class="btn btn-secondary btn-sm"
                                            style="padding: 6px; border-radius: 6px;" title="Ver detalhes">
                                            <x-heroicon-o-eye class="w-4 h-4" />
                                        </a>
                                        <a href="{{ route('sales.pdf', $sale) }}" class="btn btn-secondary btn-sm"
                                            style="padding: 6px; border-radius: 6px;" title="PDF" target="_blank">
                                            <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px 20px; color: #64748b;">
                                    <x-heroicon-o-shopping-bag class="w-10 h-10"
                                        style="margin: 0 auto 12px; color: #cbd5e1;" />
                                    <p style="font-weight: 500;">Nenhuma venda encontrada</p>
                                    <p style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Tente alterar os filtros de busca.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($sales->hasPages())
                <div style="border-top: 1px solid #e2e8f0; padding: 8px 16px;">
                    {{ $sales->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection