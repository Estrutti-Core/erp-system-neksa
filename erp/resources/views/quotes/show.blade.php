@extends('layouts.app')

@section('title', 'Detalhes do Orçamento')

@section('content')
<div style="max-width: 1000px; margin: 0 auto; padding-bottom: 60px;">
    <!-- Alertas de Sucesso ou Erro -->
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

    <!-- Cabeçalho de Ações -->
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('quotes.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>
        
        <div class="flex gap-2">
            <a href="{{ route('quotes.pdf', $quote) }}" class="btn btn-secondary" style="border-radius: 8px;" target="_blank">
                <x-heroicon-o-document-arrow-down class="w-4 h-4"/> PDF Cliente
            </a>
            <a href="{{ route('quotes.pdf', [$quote, 'mode' => 'operational']) }}" class="btn btn-secondary" style="border-radius: 8px;" target="_blank">
                <x-heroicon-o-wrench class="w-4 h-4"/> Ficha de Campo
            </a>
            @if(!$quote->isConverted())
                @can('update', $quote)
                    <a href="{{ route('quotes.edit', $quote) }}" class="btn btn-secondary" style="border-radius: 8px;">
                        <x-heroicon-o-pencil class="w-4 h-4"/> Editar Orçamento
                    </a>
                @endcan
            @endif
        </div>
    </div>

    <!-- Status Banner de Conversão -->
    @if($quote->isConverted())
        <div style="background: linear-gradient(135deg, #ede9fe 0%, #dbeafe 100%); border: 1px solid #c084fc; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
            <div class="flex items-center gap-3">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: #c084fc; color: #fff; display: flex; align-items: center; justify-content: center;">
                    <x-heroicon-o-arrow-path-rounded-square class="w-6 h-6"/>
                </div>
                <div>
                    <h3 style="font-size: 16px; font-weight: 700; color: #581c87;">Orçamento Convertido</h3>
                    <p style="font-size: 13px; color: #6b21a8; margin-top: 2px;">
                        Este orçamento foi processado em <strong>{{ $quote->converted_at->format('d/m/Y \à\s H:i') }}</strong>.
                        @if($quote->type === 'sale')
                            O faturamento comercial gerou a 
                            <a href="{{ route('sales.show', $quote->sale?->id ?? '') }}" style="font-weight: 700; text-decoration: underline; color: #4c1d95;">Venda Comercial</a>.
                        @else
                            A execução técnica gerou uma 
                            <a href="{{ route('service-orders.show', $quote->serviceOrder?->id ?? '') }}" style="font-weight: 700; text-decoration: underline; color: #4c1d95;">Ordem de Serviço</a>.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Grid Layout -->
    <div style="display: grid; grid-template-columns: 2.2fr 1fr; gap: 24px;">
        <div>
            <!-- Assistente Inteligente de Faturamento e Conversão -->
            @if(!$quote->isConverted())
                @php
                    $hasServices = $quote->items->contains(fn($item) => $item->isService());
                @endphp
                
                <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; background: #fff; overflow: hidden; position: relative;">
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(to right, #4f46e5, #10b981);"></div>
                    
                    <div class="flex items-start justify-between flex-wrap gap-4">
                        <div style="flex: 1; min-width: 250px;">
                            @if($hasServices)
                                <div style="display: inline-flex; align-items: center; gap: 6px; background: #faf5ff; color: #6b21a8; padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: 700; border: 1px solid #f3e8ff; margin-bottom: 12px;">
                                    <x-heroicon-o-wrench-screwdriver class="w-4 h-4"/> Fluxo Operacional Recomendado
                                </div>
                                <h3 style="font-size: 16px; font-weight: 800; color: #0f172a;">Faturamento Técnico: Ordem de Serviço</h3>
                                <p style="font-size: 13px; color: #475569; line-height: 1.6; margin-top: 4px; margin-bottom: 0;">
                                    Este orçamento possui <strong>serviços técnicos</strong> cadastrados. Para realizar o atendimento em campo e a execução técnica, converta esta proposta em uma Ordem de Serviço.
                                </p>
                            @else
                                <div style="display: inline-flex; align-items: center; gap: 6px; background: #ecfdf5; color: #047857; padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: 700; border: 1px solid #d1fae5; margin-bottom: 12px;">
                                    <x-heroicon-o-shopping-bag class="w-4 h-4"/> Fluxo Comercial Recomendado
                                </div>
                                <h3 style="font-size: 16px; font-weight: 800; color: #0f172a;">Faturamento Comercial: Venda Direta</h3>
                                <p style="font-size: 13px; color: #475569; line-height: 1.6; margin-top: 4px; margin-bottom: 0;">
                                    Este orçamento contém <strong>apenas produtos físicos</strong>. A recomendação é realizar o faturamento comercial direto, gerando a baixa de estoque correspondente.
                                </p>
                            @endif
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 10px; width: 100%; max-width: 280px; justify-content: center;">
                            <!-- Botão OS -->
                            @if($hasServices)
                                <form method="POST" action="{{ route('quotes.convert', $quote) }}">
                                    @csrf
                                    <input type="hidden" name="destination_type" value="service_order">
                                    <button type="submit" class="btn btn-primary w-full text-center flex items-center justify-center gap-2" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none; border-radius: 8px; padding: 12px; font-weight: 700; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2); transition: all 0.2s;">
                                        <x-heroicon-o-wrench-screwdriver class="w-5 h-5"/> Gerar Ordem de Serviço
                                    </button>
                                </form>
                                
                                <button type="button" class="btn btn-secondary w-full flex items-center justify-center gap-2" style="opacity: 0.5; cursor: not-allowed; background: #f1f5f9; border: 1px solid #cbd5e1; color: #94a3b8; padding: 12px;" disabled title="Não permitido para orçamentos com serviços.">
                                    <x-heroicon-o-lock-closed class="w-4 h-4"/> Gerar Venda
                                </button>
                                <span style="font-size: 10px; color: #94a3b8; text-align: center; display: block; margin-top: -4px;">Venda indisponível para propostas com serviços.</span>
                            @else
                                <form method="POST" action="{{ route('quotes.convert', $quote) }}">
                                    @csrf
                                    <input type="hidden" name="destination_type" value="sale">
                                    <button type="submit" class="btn btn-primary w-full text-center flex items-center justify-center gap-2" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; border-radius: 8px; padding: 12px; font-weight: 700; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); transition: all 0.2s;">
                                        <x-heroicon-o-shopping-bag class="w-5 h-5"/> Faturar Venda Comercial
                                    </button>
                                </form>
                                
                                <button type="button" class="btn btn-secondary w-full flex items-center justify-center gap-2" style="opacity: 0.5; cursor: not-allowed; background: #f1f5f9; border: 1px solid #cbd5e1; color: #94a3b8; padding: 12px;" disabled title="Ordem de Serviço exige pelo menos um serviço cadastrado.">
                                    <x-heroicon-o-lock-closed class="w-4 h-4"/> Gerar OS
                                </button>
                                <span style="font-size: 10px; color: #94a3b8; text-align: center; display: block; margin-top: -4px;">OS exige pelo menos 1 item do tipo Serviço.</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Detalhes do Orçamento -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
                <div class="flex items-center justify-between mb-4 border-bottom pb-3">
                    <div>
                        <span class="badge badge-{{ $quote->status->color() }}">{{ $quote->status->label() }}</span>
                        <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 6px;">Orçamento {{ $quote->code }}</h2>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 12px; color: #64748b;">Criado em</span>
                        <div style="font-weight: 600; color: #334155; font-size: 14px;">{{ $quote->created_at->format('d/m/Y') }}</div>
                    </div>
                </div>

                <!-- Tabela de Itens -->
                <h3 style="font-size: 14px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Itens Vinculados</h3>
                <div class="table-wrap mb-4" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                    <table>
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="padding: 10px 14px;">Descrição</th>
                                <th>Tipo</th>
                                <th style="text-align: center;">Qtd</th>
                                <th style="text-align: right;">Preço Unit.</th>
                                <th style="text-align: right; padding-right: 14px;">Preço Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quote->items as $item)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 12px 14px;">
                                        <div style="font-weight: 600; color: #1e293b;">{{ $item->description }}</div>
                                        <div style="font-size: 11px; color: #64748b;">SKU: {{ $item->product?->sku ?: '—' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $item->type->color() }}">
                                            {{ $item->type->label() }}
                                        </span>
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
                            <span>Subtotal</span>
                            <span style="font-weight: 600; color: #334155;">R$ {{ number_format($quote->items_amount, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center mb-3" style="font-size: 13px; color: #ef4444;">
                            <span>Desconto</span>
                            <span style="font-weight: 600;">- R$ {{ number_format($quote->discount_amount, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-3 border-t" style="font-size: 14px; font-weight: 700; color: #0f172a;">
                            <span>Valor Total</span>
                            <span style="color: #4f46e5; font-size: 18px;">R$ {{ number_format($quote->total_amount, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dados de Logística & Prazos -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
                <h3 style="font-size: 14px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">Dados de Logística & Prazos</h3>
                <div class="grid-2">
                    <div>
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Transportadora</div>
                        <div style="font-weight: 600; color: #1e293b; font-size: 14px;">{{ $quote->carrier ?: 'Não informada' }}</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Modalidade do Frete</div>
                        <div style="font-weight: 600; color: #1e293b; font-size: 14px;">
                            @if($quote->freight_type === 0) Contratação do Frete por conta do Remetente (CIF)
                            @elseif($quote->freight_type === 1) Contratação do Frete por conta do Destinatário (FOB)
                            @elseif($quote->freight_type === 2) Contratação do Frete por conta de Terceiros
                            @elseif($quote->freight_type === 3) Transporte Próprio por conta do Remetente
                            @elseif($quote->freight_type === 4) Transporte Próprio por conta do Destinatário
                            @else Sem Frete
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid-3 mt-4">
                    <div>
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Valor do Frete</div>
                        <div style="font-weight: 600; color: #1e293b; font-size: 14px;">R$ {{ number_format($quote->freight_price, 2, ',', '.') }}</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Volumes</div>
                        <div style="font-weight: 600; color: #1e293b; font-size: 14px;">{{ $quote->volume ?: '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Peso (Bruto / Líquido)</div>
                        <div style="font-weight: 600; color: #1e293b; font-size: 14px;">
                            {{ $quote->weight_gross ? number_format($quote->weight_gross, 4, ',', '.') . ' kg' : '—' }} /
                            {{ $quote->weight_net ? number_format($quote->weight_net, 4, ',', '.') . ' kg' : '—' }}
                        </div>
                    </div>
                </div>

                <div class="grid-3 mt-4 font-normal">
                    <div>
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Prazo de Entrega</div>
                        <div style="font-weight: 600; color: #1e293b; font-size: 14px;">{{ $quote->delivery_deadline ?: '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Garantia Comercial</div>
                        <div style="font-weight: 600; color: #1e293b; font-size: 14px;">{{ $quote->warranty ?: '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 2px;">Validade da Proposta</div>
                        <div style="font-weight: 600; color: #1e293b; font-size: 14px;">{{ $quote->validity ?: '—' }}</div>
                    </div>
                </div>
            </div>

            <!-- Observações -->
            @if($quote->notes)
                <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px;">
                    <h3 style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Observações da Proposta</h3>
                    <p style="font-size: 13px; color: #334155; line-height: 1.6;">{!! nl2br(e($quote->notes)) !!}</p>
                </div>
            @endif
        </div>

        <!-- Sidebar Lateral -->
        <div>
            <!-- Cliente Card -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px;">
                <h3 style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Cliente</h3>
                <div style="font-weight: 700; color: #0f172a; font-size: 15px;">{{ $quote->client->name }}</div>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">CPF/CNPJ: {{ $quote->client->formatted_document }}</div>
                
                <div style="border-top: 1px solid #f1f5f9; margin-top: 12px; padding-top: 12px;">
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 600; text-transform: uppercase;">Local de Atendimento</div>
                    <div style="font-size: 12px; color: #334155; margin-top: 4px; line-height: 1.4;">
                        {{ $quote->clientAddress->street }}, {{ $quote->clientAddress->number }}<br>
                        {{ $quote->clientAddress->neighborhood }}<br>
                        {{ $quote->clientAddress->city }} / {{ $quote->clientAddress->state }}
                    </div>
                </div>

                @if($quote->equipment)
                    <div style="border-top: 1px solid #f1f5f9; margin-top: 12px; padding-top: 12px;">
                        <div style="font-size: 11px; color: #94a3b8; font-weight: 600; text-transform: uppercase;">Equipamento para O.S.</div>
                        <div style="font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 4px;">{{ $quote->equipment->name }}</div>
                        @if($quote->equipment->brand || $quote->equipment->model)
                            <div style="font-size: 12px; color: #475569;">
                                {{ $quote->equipment->brand }}{{ $quote->equipment->brand && $quote->equipment->model ? ' - ' : '' }}{{ $quote->equipment->model }}
                            </div>
                        @endif
                        @if($quote->equipment->serial_number)
                            <div style="font-size: 11px; color: #64748b; font-family: monospace; margin-top: 2px;">S/N: {{ $quote->equipment->serial_number }}</div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Validade & Prazos -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px;">
                <h3 style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Validade</h3>
                @if($quote->valid_until)
                    <div style="font-weight: 600; font-size: 14px; color: {{ $quote->valid_until->isPast() && !$quote->isConverted() ? '#ef4444' : '#1e293b' }}">
                        Expira em {{ $quote->valid_until->format('d/m/Y') }}
                        @if($quote->valid_until->isPast() && !$quote->isConverted())
                            <div style="font-size: 11px; color: #ef4444; font-weight: 700; margin-top: 2px;">PROPOSTA EXPIRADA</div>
                        @endif
                    </div>
                @else
                    <div style="font-size: 13px; color: #64748b;">Orçamento sem validade definida</div>
                @endif
            </div>

            <!-- Notas Internas -->
            @if($quote->internal_notes)
                <div class="card shadow-sm mt-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; background: #faf5ff;">
                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; color: #6b21a8; font-weight: 700; font-size: 13px;">
                        <x-heroicon-o-lock-closed class="w-4 h-4"/> Notas Internas
                    </div>
                    <p style="font-size: 12px; color: #581c87; line-height: 1.5;">{{ $quote->internal_notes }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
