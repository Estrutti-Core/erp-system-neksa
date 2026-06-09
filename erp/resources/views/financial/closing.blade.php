@extends('layouts.app')

@section('title', 'Fechamento Financeiro Mensal')

@section('content')
<style>
    @media print {
        body {
            background: #ffffff !important;
            color: #000000 !important;
            font-size: 12px !important;
        }
        .no-print, header, nav, .sidebar, .sidebar-wrapper, .navbar, .btn, form, .breadcrumbs, .filter-card {
            display: none !important;
        }
        .main-content, .content-wrapper {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        .card {
            border: 1px solid #cbd5e1 !important;
            box-shadow: none !important;
            margin-bottom: 20px !important;
            page-break-inside: avoid;
        }
        .print-header {
            display: block !important;
            margin-bottom: 24px;
            border-bottom: 2px solid #334155;
            padding-bottom: 12px;
        }
        .print-title {
            font-size: 20px !important;
            font-weight: 800 !important;
            color: #1e293b !important;
            text-align: center;
        }
        .print-subtitle {
            font-size: 11px !important;
            color: #64748b !important;
            text-align: center;
        }
    }
    .print-header {
        display: none;
    }
</style>

<div class="space-y-6">
    <!-- Cabeçalho de Impressão (Exibido apenas na impressão) -->
    <div class="print-header">
        <div class="print-title">Relatório de Fechamento Financeiro Mensal</div>
        <div class="print-subtitle">Mês de Referência: {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('m/Y') }} | Gerado em: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <!-- Filtros e Ações de Exportação (Não impresso) -->
    <div class="card no-print filter-card">
        <div class="card-body flex flex-col md:flex-row md:items-end justify-between gap-4">
            <form method="GET" action="{{ route('financial.closing') }}" class="flex flex-col md:flex-row md:items-end gap-4 flex-1">
                <div style="min-width: 240px;">
                    <label for="month" class="form-label" style="font-weight: 600; font-size: 12px;">Selecione o Mês de Referência</label>
                    <input type="month" name="month" id="month" class="form-control mt-1" value="{{ $month }}" onchange="this.form.submit()">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Atualizar Relatório</button>
                </div>
            </form>
            <div class="flex items-center gap-2">
                <!-- Exportar XLSX -->
                <a href="{{ route('financial.closing.xlsx', ['month' => $month]) }}" class="btn btn-success flex items-center gap-2" style="background-color: #16a34a; border-color: #16a34a; color: white;">
                    <x-heroicon-o-document-arrow-down class="w-5 h-5" /> Exportar Planilha (XLSX)
                </a>
                <!-- Visualizar/Imprimir PDF -->
                <a href="{{ route('financial.closing.pdf', ['month' => $month]) }}" target="_blank" class="btn btn-primary flex items-center gap-2" style="background-color: #2563eb; border-color: #2563eb; color: white;">
                    <x-heroicon-o-printer class="w-5 h-5" /> Ficha A4 (PDF)
                </a>
                <!-- Impressão Direta -->
                <button onclick="window.print()" class="btn btn-secondary flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-5 h-5" /> Imprimir Relatório
                </button>
            </div>
        </div>
    </div>

    <!-- Comparativo Caixa vs Competência -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Regime de Caixa -->
        <div class="card">
            <div class="card-header border-b border-gray-100 pb-3 mb-4">
                <h3 class="font-bold text-lg text-slate-800 flex items-center gap-2">
                    <x-heroicon-o-banknotes class="w-5 h-5 text-green-600" /> Fluxo de Caixa (Regime de Caixa)
                </h3>
                <p class="text-xs text-slate-500">Com base nas datas de pagamento/recebimento efetivo dentro do mês.</p>
            </div>
            <div class="card-body space-y-4">
                <div class="flex justify-between p-3 rounded" style="background-color: #f0fdf4;">
                    <span class="text-sm font-semibold text-green-900">Total de Recebimentos (Entradas)</span>
                    <strong class="text-green-900" style="font-family: monospace;">R$ {{ number_format($cashInflow, 2, ',', '.') }}</strong>
                </div>
                <div class="flex justify-between p-3 rounded" style="background-color: #fef2f2;">
                    <span class="text-sm font-semibold text-red-900">Total de Pagamentos (Saídas)</span>
                    <strong class="text-red-900" style="font-family: monospace;">R$ {{ number_format($cashOutflow, 2, ',', '.') }}</strong>
                </div>
                <div class="flex justify-between p-4 rounded border-t-2" style="background-color: {{ $cashBalance >= 0 ? '#ecfdf5' : '#fff1f2' }}; border-color: {{ $cashBalance >= 0 ? '#10b981' : '#f43f5e' }};">
                    <span class="text-md font-bold text-slate-800">Saldo Líquido de Caixa</span>
                    <strong class="text-lg @if($cashBalance >= 0) text-green-700 @else text-red-700 @endif" style="font-family: monospace;">R$ {{ number_format($cashBalance, 2, ',', '.') }}</strong>
                </div>
            </div>
        </div>

        <!-- Regime de Competência -->
        <div class="card">
            <div class="card-header border-b border-gray-100 pb-3 mb-4">
                <h3 class="font-bold text-lg text-slate-800 flex items-center gap-2">
                    <x-heroicon-o-calendar class="w-5 h-5 text-blue-600" /> Resultado Operacional (Competência)
                </h3>
                <p class="text-xs text-slate-500">Com base nas datas de faturamento do título no mês, independente de pago.</p>
            </div>
            <div class="card-body space-y-4">
                <div class="flex justify-between p-3 rounded" style="background-color: #f0f9ff;">
                    <span class="text-sm font-semibold text-blue-900">Receitas Faturadas (Competência)</span>
                    <strong class="text-blue-900" style="font-family: monospace;">R$ {{ number_format($accrualInflow, 2, ',', '.') }}</strong>
                </div>
                <div class="flex justify-between p-3 rounded" style="background-color: #fafaf9;">
                    <span class="text-sm font-semibold text-slate-900">Despesas Lançadas (Competência)</span>
                    <strong class="text-slate-900" style="font-family: monospace;">R$ {{ number_format($accrualOutflow, 2, ',', '.') }}</strong>
                </div>
                <div class="flex justify-between p-4 rounded border-t-2" style="background-color: {{ $accrualBalance >= 0 ? '#eff6ff' : '#fff1f2' }}; border-color: {{ $accrualBalance >= 0 ? '#3b82f6' : '#f43f5e' }};">
                    <span class="text-md font-bold text-slate-800">Resultado Operacional</span>
                    <strong class="text-lg @if($accrualBalance >= 0) text-blue-700 @else text-red-700 @endif" style="font-family: monospace;">R$ {{ number_format($accrualBalance, 2, ',', '.') }}</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Cálculo do Simples Nacional Baseado no RBT12 -->
    <div class="card">
        <div class="card-header border-b border-gray-100 pb-3 mb-4">
            <h3 class="font-bold text-lg text-slate-800 flex items-center gap-2">
                <x-heroicon-o-calculator class="w-5 h-5 text-violet-600" /> Cálculo da Alíquota Efetiva do Simples Nacional (RBT12)
            </h3>
            <p class="text-xs text-slate-500">Cálculo simulado de impostos conforme as faixas acumuladas nos últimos 12 meses anteriores ao período de apuração.</p>
        </div>
        <div class="card-body space-y-6">
            <!-- KPIs Fiscais -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div class="p-4 rounded border" style="background-color: #f5f3ff; border-color: #ddd6fe;">
                    <span class="text-xs text-slate-600 font-bold block uppercase">Faturamento RBT12 Acumulado</span>
                    <div class="text-xl font-black text-violet-800 mt-1" style="font-family: monospace;">
                        R$ {{ number_format($rbt12Total, 2, ',', '.') }}
                    </div>
                    <span class="text-[10px] text-slate-500 mt-1 block">12 meses anteriores à apuração</span>
                </div>
                <div class="p-4 rounded border" style="background-color: #f0fdf4; border-color: #bbf7d0;">
                    <span class="text-xs text-slate-600 font-bold block uppercase">Alíquota Comércio (Anexo I)</span>
                    <div class="text-xl font-black text-green-800 mt-1" style="font-family: monospace;">
                        {{ number_format($comercioEffectiveRate, 2, ',', '.') }}%
                    </div>
                    <span class="text-[10px] text-slate-500 mt-1 block">Alíquota efetiva calculada</span>
                </div>
                <div class="p-4 rounded border" style="background-color: #eff6ff; border-color: #bfdbfe;">
                    <span class="text-xs text-slate-600 font-bold block uppercase">Alíquota Serviços (Anexo III)</span>
                    <div class="text-xl font-black text-blue-800 mt-1" style="font-family: monospace;">
                        {{ number_format($servicosEffectiveRate, 2, ',', '.') }}%
                    </div>
                    <span class="text-[10px] text-slate-500 mt-1 block">Alíquota efetiva calculada</span>
                </div>
                <div class="p-4 rounded border" style="background-color: #fffbeb; border-color: #fde68a;">
                    <span class="text-xs text-slate-600 font-bold block uppercase">Imposto Total Devido (Mês)</span>
                    <div class="text-xl font-black text-amber-800 mt-1" style="font-family: monospace;">
                        R$ {{ number_format($totalTaxDue, 2, ',', '.') }}
                    </div>
                    <span class="text-[10px] text-slate-500 mt-1 block">Tributação total estimada do período</span>
                </div>
            </div>

            <!-- Tabelas de Apuração de Faturamento e Imposto do Mês -->
            <div class="table-responsive border rounded mt-4">
                <table class="table mb-0">
                    <thead>
                        <tr style="background-color: #f8fafc;">
                            <th>Tipo de Faturamento</th>
                            <th>Regra Fiscal</th>
                            <th style="text-align: right">Faturamento Mês</th>
                            <th style="text-align: right">Alíquota Efetiva</th>
                            <th style="text-align: right">Provisão de Imposto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Comércio (Vendas)</strong></td>
                            <td class="text-xs text-slate-500">Anexo I do Simples Nacional</td>
                            <td style="text-align: right; font-family: monospace;">R$ {{ number_format($revenueComercio, 2, ',', '.') }}</td>
                            <td style="text-align: right; font-family: monospace;">{{ number_format($comercioEffectiveRate, 2, ',', '.') }}%</td>
                            <td style="text-align: right; font-family: monospace;" class="text-green-800 font-bold">R$ {{ number_format($taxComercio, 2, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td><strong>Prestação de Serviços (OS)</strong></td>
                            <td class="text-xs text-slate-500">Anexo III do Simples Nacional</td>
                            <td style="text-align: right; font-family: monospace;">R$ {{ number_format($revenueServicos, 2, ',', '.') }}</td>
                            <td style="text-align: right; font-family: monospace;">{{ number_format($servicosEffectiveRate, 2, ',', '.') }}%</td>
                            <td style="text-align: right; font-family: monospace;" class="text-blue-800 font-bold">R$ {{ number_format($revenueServicos * ($servicosEffectiveRate / 100), 2, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td><strong>Receitas Operacionais Avulsas</strong></td>
                            <td class="text-xs text-slate-500">Tributado no Anexo III por padrão</td>
                            <td style="text-align: right; font-family: monospace;">R$ {{ number_format($revenueAvulsa, 2, ',', '.') }}</td>
                            <td style="text-align: right; font-family: monospace;">{{ number_format($servicosEffectiveRate, 2, ',', '.') }}%</td>
                            <td style="text-align: right; font-family: monospace;" class="text-slate-800 font-bold">R$ {{ number_format($revenueAvulsa * ($servicosEffectiveRate / 100), 2, ',', '.') }}</td>
                        </tr>
                        <tr style="background-color: #f1f5f9; font-weight: 800;">
                            <td colspan="2">Totais Consolidados</td>
                            <td style="text-align: right; font-family: monospace;">R$ {{ number_format($totalRevenueMonth, 2, ',', '.') }}</td>
                            <td style="text-align: right;">-</td>
                            <td style="text-align: right; font-family: monospace;" class="text-amber-800">R$ {{ number_format($totalTaxDue, 2, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabela Detalhada de Caixa do Mês (Entradas e Saídas) -->
    <div class="card">
        <div class="card-header border-b border-gray-100 pb-3 mb-4">
            <h3 class="font-bold text-lg text-slate-800">Lançamentos Financeiros do Período (Regime de Caixa)</h3>
            <p class="text-xs text-slate-500">Detalhamento dos recebimentos e pagamentos efetuados dentro do mês selecionado.</p>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr style="background-color: #f8fafc;">
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Descrição</th>
                            <th>Meio de Pgto</th>
                            <th style="text-align: right">Valor Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cashEntries as $entry)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($entry->date)->format('d/m/Y') }}</td>
                                <td>
                                    @if($entry->type === 'receita')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-800" style="background-color: #d1fae5; color: #065f46;">Receita</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800" style="background-color: #fee2e2; color: #991b1b;">Despesa</span>
                                    @endif
                                </td>
                                <td>{{ $entry->description }}</td>
                                <td>{{ ucfirst($entry->payment_method ?? 'Outro') }}</td>
                                <td style="text-align: right; font-family: monospace;" class="@if($entry->type === 'receita') text-green-700 font-semibold @else text-red-700 @endif">
                                    @if($entry->type === 'receita')+@else-@endif R$ {{ number_format($entry->amount, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 32px;" class="text-slate-500">
                                    Nenhum lançamento de caixa registrado neste período.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
