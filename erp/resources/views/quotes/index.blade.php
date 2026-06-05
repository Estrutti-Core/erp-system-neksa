@extends('layouts.app')

@section('title', 'Orçamentos')

@section('topbar-actions')
    @can('create', App\Models\Quote::class)
        <a href="{{ route('quotes.create') }}" class="btn btn-primary shadow-sm"
            style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);">
            <x-heroicon-o-plus class="w-4 h-4" /> Novo Orçamento
        </a>
    @endcan
@endsection

@section('content')
    <div style="max-width: 1200px; margin: 0 auto;">
        <!-- Filtros -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; padding: 16px;">
            <form method="GET" action="{{ route('quotes.index') }}"
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
                        <a href="{{ route('quotes.index') }}" class="btn btn-secondary btn-sm"
                            style="border-radius: 8px; color: #ef4444;">Limpar</a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Tabela de Orçamentos -->
        <div class="card shadow-sm" style="border-radius: 12px; padding: 0; overflow: hidden; border: 1px solid #e2e8f0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="font-weight: 600; padding: 14px 20px;">Código</th>
                            <th>Cliente</th>
                            <th>Validade</th>
                            <th>Valor Total</th>
                            <th>Destino</th>
                            <th>Status</th>
                            <th style="text-align: right; padding-right: 20px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quotes as $quote)
                            <tr>
                                <td style="padding: 14px 20px; font-weight: 700; color: #4f46e5; font-family: monospace;">
                                    {{ $quote->code }}
                                </td>
                                <td>
                                    <div>
                                        <div style="font-weight: 600; color: #1e293b;">{{ $quote->client->name }}</div>
                                        <div style="font-size: 11px; color: #64748b;">CPF/CNPJ:
                                            {{ $quote->client->formatted_document }}</div>
                                    </div>
                                </td>
                                <td>
                                    @if($quote->valid_until)
                                        <span
                                            style="color: {{ $quote->valid_until->isPast() && !$quote->isConverted() ? '#ef4444' : '#475569' }}; font-weight: 500;">
                                            {{ $quote->valid_until->format('d/m/Y') }}
                                            @if($quote->valid_until->isPast() && !$quote->isConverted())
                                                <span
                                                    style="font-size: 10px; display: block; font-weight: 600; text-transform: uppercase;">Expirado</span>
                                            @endif
                                        </span>
                                    @else
                                        <span style="color: #94a3b8;">Sem data</span>
                                    @endif
                                </td>
                                <td style="font-weight: 700; color: #0f172a;">R$
                                    {{ number_format($quote->total_amount, 2, ',', '.') }}</td>
                                <td>
                                    @if($quote->isConverted())
                                        <span class="badge badge-violet" style="font-size: 11px;">
                                            {{ $quote->type === 'sale' ? 'Venda' : 'OS' }}
                                        </span>
                                    @else
                                        <span style="color: #94a3b8;">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-{{ $quote->status->color() }}">
                                        {{ $quote->status->label() }}
                                    </span>
                                </td>
                                <td style="text-align: right; padding-right: 20px;">
                                    <div class="flex gap-2 justify-end">
                                        <a href="{{ route('quotes.show', $quote) }}" class="btn btn-secondary btn-sm"
                                            style="padding: 6px; border-radius: 6px;" title="Ver detalhes">
                                            <x-heroicon-o-eye class="w-4 h-4" />
                                        </a>
                                        <a href="{{ route('quotes.pdf', $quote) }}" class="btn btn-secondary btn-sm"
                                            style="padding: 6px; border-radius: 6px;" title="PDF" target="_blank">
                                            <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                        </a>
                                        @if(!$quote->isConverted())
                                            @can('update', $quote)
                                                <a href="{{ route('quotes.edit', $quote) }}" class="btn btn-secondary btn-sm"
                                                    style="padding: 6px; border-radius: 6px;" title="Editar">
                                                    <x-heroicon-o-pencil class="w-4 h-4" />
                                                </a>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px 20px; color: #64748b;">
                                    <x-heroicon-o-document-text class="w-10 h-10"
                                        style="margin: 0 auto 12px; color: #cbd5e1;" />
                                    <p style="font-weight: 500;">Nenhum orçamento encontrado</p>
                                    <p style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Tente alterar os filtros ou
                                        crie um novo orçamento.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($quotes->hasPages())
                <div style="border-top: 1px solid #e2e8f0; padding: 8px 16px;">
                    {{ $quotes->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection