@extends('layouts.app')

@section('title', 'Detalhes da Venda')

@section('content')
<div style="max-width: 1000px; margin: 0 auto; padding-bottom: 60px;">
    <!-- Alertas -->
    @if(session('success'))
        <div style="background: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; padding: 14px; margin-bottom: 20px; color: #065f46; font-weight: 500; font-size: 14px;" class="flex items-center gap-2">
            <x-heroicon-o-check-circle class="w-5 h-5" style="color: #10b981;"/>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="background: #fef2f2; border: 1px solid #ef4444; border-radius: 8px; padding: 14px; margin-bottom: 20px; color: #991b1b; font-weight: 500; font-size: 14px;" class="flex items-center gap-2">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5" style="color: #ef4444;"/>
            {{ session('error') }}
        </div>
    @endif

    <!-- Cabeçalho -->
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('sales.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>

        <div class="flex gap-2">
            <a href="{{ route('sales.pdf', $sale) }}" class="btn btn-secondary" style="border-radius: 8px;" target="_blank">
                <x-heroicon-o-document-arrow-down class="w-4 h-4"/> PDF
            </a>
            @can('update', $sale)
                @if($sale->status !== App\Enums\SaleStatus::Cancelled)
                    <form method="POST" action="{{ route('sales.cancel', $sale) }}" onsubmit="return confirm('Tem certeza que deseja CANCELAR esta venda? Isso estornará o estoque automaticamente.');">
                        @csrf
                        <button type="submit" class="btn btn-danger flex items-center gap-2" style="border-radius: 8px;">
                            <x-heroicon-o-x-mark class="w-4 h-4"/> Cancelar Venda
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <!-- Grid Layout -->
    <div style="display: grid; grid-template-columns: 2.2fr 1fr; gap: 24px;">
        <div>
            <!-- Resumo da Venda -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
                <div class="flex items-center justify-between mb-4 border-bottom pb-3">
                    <div>
                        <span class="badge badge-{{ $sale->status->color() }}">{{ $sale->status->label() }}</span>
                        <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 6px;">Venda {{ $sale->code }}</h2>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 12px; color: #64748b;">Faturado em</span>
                        <div style="font-weight: 600; color: #334155; font-size: 14px;">{{ $sale->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                </div>

                <!-- Itens Faturados -->
                <h3 style="font-size: 14px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Itens da Venda</h3>
                <div class="table-wrap mb-4" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                    <table>
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="padding: 10px 14px;">Descrição</th>
                                <th style="text-align: center;">Qtd</th>
                                <th style="text-align: right;">Preço Unit.</th>
                                <th style="text-align: right; padding-right: 14px;">Preço Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sale->items as $item)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 12px 14px;">
                                        <div style="font-weight: 600; color: #1e293b;">{{ $item->description }}</div>
                                        <div style="font-size: 11px; color: #64748b;">SKU: {{ $item->product?->sku ?: '—' }}</div>
                                    </td>
                                    <td style="text-align: center; font-weight: 500; color: #334155;">{{ number_format($item->quantity, 0, ',', '.') }} {{ $item->unit }}</td>
                                    <td style="text-align: right; font-family: monospace; color: #334155;">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                                    <td style="text-align: right; padding-right: 14px; font-family: monospace; font-weight: 700; color: #0f172a;">R$ {{ number_format($item->total_price, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Resumo Financeiro -->
                <div style="display: flex; justify-content: flex-end;">
                    <div style="width: 280px;">
                        <div class="flex justify-between items-center mb-2" style="font-size: 13px; color: #64748b;">
                            <span>Subtotal Itens</span>
                            <span style="font-weight: 600; color: #334155;">R$ {{ number_format($sale->items_amount, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center mb-3" style="font-size: 13px; color: #ef4444;">
                            <span>Desconto Aplicado</span>
                            <span style="font-weight: 600;">- R$ {{ number_format($sale->discount_amount, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-3 border-t" style="font-size: 14px; font-weight: 700; color: #0f172a;">
                            <span>Total Líquido</span>
                            <span style="color: #10b981; font-size: 20px; font-weight: 800;">R$ {{ number_format($sale->total_amount, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notas Fiscais Auxiliares -->
            <div class="card shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px;">
                <div class="flex items-center gap-2 mb-3" style="color: #3b82f6; font-weight: 700; font-size: 14px;">
                    <x-heroicon-o-document-chart-bar class="w-5 h-5"/> Guia para Emissão Fiscal Manual (NFe)
                </div>
                <p style="font-size: 12px; color: #64748b; line-height: 1.5; margin-bottom: 12px;">
                    Para realizar a emissão da Nota Fiscal Eletrônica referente a esta venda, utilize os parâmetros tributários abaixo no seu emissor de preferência:
                </p>

                <div style="background: #f8fafc; border-radius: 8px; padding: 12px; font-size: 12px; line-height: 1.6;">
                    <div><strong>CFOP Consolidado:</strong> 5102 (Venda dentro do estado) / 6102 (Fora do estado)</div>
                    <div><strong>Regime Tributário:</strong> Simples Nacional</div>
                    <div><strong>CSOSN Indicado:</strong> 102 (Sem permissão de crédito) ou 400 (Isenta de ICMS)</div>
                </div>
            </div>
        </div>

        <!-- Sidebar Lateral -->
        <div>
            <!-- Cliente -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px;">
                <h3 style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Faturado para</h3>
                <div style="font-weight: 700; color: #0f172a; font-size: 15px;">{{ $sale->client->name }}</div>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">CPF/CNPJ: {{ $sale->client->formatted_document }}</div>
                
                <div style="border-top: 1px solid #f1f5f9; margin-top: 12px; padding-top: 12px;">
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 600; text-transform: uppercase;">Local de Entrega</div>
                    <div style="font-size: 12px; color: #334155; margin-top: 4px; line-height: 1.4;">
                        {{ $sale->clientAddress->street }}, {{ $sale->clientAddress->number }}<br>
                        {{ $sale->clientAddress->neighborhood }}<br>
                        {{ $sale->clientAddress->city }} / {{ $sale->clientAddress->state }}
                    </div>
                </div>
            </div>

            <!-- Origem do Orçamento -->
            @if($sale->quote)
                <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px;">
                    <h3 style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Orçamento Origem</h3>
                    <a href="{{ route('quotes.show', $sale->quote) }}" class="flex items-center gap-2" style="font-weight: 700; color: #4f46e5; font-size: 14px;">
                        <x-heroicon-o-document-text class="w-5 h-5"/> {{ $sale->quote->code }}
                    </a>
                </div>
            @endif

            <!-- Notas de Observação -->
            @if($sale->notes)
                <div class="card shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; background: #faf5ff;">
                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; color: #6b21a8; font-weight: 700; font-size: 13px;">
                        <x-heroicon-o-chat-bubble-bottom-center-text class="w-4 h-4"/> Observações da Proposta
                    </div>
                    <p style="font-size: 12px; color: #581c87; line-height: 1.5;">{{ $sale->notes }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
