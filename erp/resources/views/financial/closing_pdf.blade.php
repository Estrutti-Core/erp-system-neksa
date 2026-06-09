<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Fechamento Financeiro - {{ $month }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #334155;
            font-size: 12px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 24px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e293b;
        }
        .header-meta {
            text-align: right;
            font-size: 11px;
            color: #64748b;
        }
        .company-info {
            font-size: 11px;
            color: #475569;
            margin-top: 4px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 24px;
            margin-bottom: 12px;
        }
        .grid {
            width: 100%;
            margin-bottom: 16px;
        }
        .grid-td {
            width: 50%;
            vertical-align: top;
        }
        .card {
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 12px;
            margin-right: 8px;
        }
        .card-title {
            font-size: 12px;
            font-weight: bold;
            color: #475569;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .card-row {
            display: table;
            width: 100%;
            margin-bottom: 6px;
        }
        .card-row-label {
            display: table-cell;
            text-align: left;
            color: #475569;
        }
        .card-row-value {
            display: table-cell;
            text-align: right;
            font-weight: bold;
            font-family: monospace;
        }
        .card-total {
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
            margin-top: 6px;
            font-weight: bold;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .table th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: bold;
            text-align: left;
            padding: 8px;
            border: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .table td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .text-right {
            text-align: right;
        }
        .font-mono {
            font-family: monospace;
        }
        .text-green {
            color: #15803d;
        }
        .text-red {
            color: #b91c1c;
        }
        .text-blue {
            color: #1d4ed8;
        }
        .text-violet {
            color: #6d28d9;
        }
        .text-bold {
            font-weight: bold;
        }
    </style>
</head>
<body>

    <!-- Cabeçalho -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="header-title">FECHAMENTO FINANCEIRO MENSAL</div>
                    <div class="company-info">
                        <strong>{{ $company->name ?? 'ERP Neksa' }}</strong><br>
                        CNPJ: {{ $company->cnpj ?? 'N/A' }} | Inscrição Estadual: {{ $company->state_registration ?? 'N/A' }}
                    </div>
                </td>
                <td class="header-meta">
                    <strong>Mês de Referência:</strong> {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('m/Y') }}<br>
                    <strong>Emissão:</strong> {{ now()->format('d/m/Y H:i') }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Regime de Caixa vs Regime de Competência -->
    <table class="grid">
        <tr>
            <td class="grid-td">
                <div class="card" style="margin-right: 12px;">
                    <div class="card-title">FLUXO DE CAIXA (REGIME DE CAIXA)</div>
                    <div class="card-row">
                        <div class="card-row-label">Total Entradas (Recebidos):</div>
                        <div class="card-row-value text-green">R$ {{ number_format($cashInflow, 2, ',', '.') }}</div>
                    </div>
                    <div class="card-row">
                        <div class="card-row-label">Total Saídas (Pagos):</div>
                        <div class="card-row-value text-red">R$ {{ number_format($cashOutflow, 2, ',', '.') }}</div>
                    </div>
                    <div class="card-row card-total" style="background-color: {{ $cashBalance >= 0 ? '#f0fdf4' : '#fff1f2' }}">
                        <div class="card-row-label">Saldo Líquido de Caixa:</div>
                        <div class="card-row-value @if($cashBalance >= 0) text-green @else text-red @endif">R$ {{ number_format($cashBalance, 2, ',', '.') }}</div>
                    </div>
                </div>
            </td>
            <td class="grid-td">
                <div class="card" style="margin-left: 12px;">
                    <div class="card-title">RESULTADO OPERACIONAL (COMPETÊNCIA)</div>
                    <div class="card-row">
                        <div class="card-row-label">Total Receitas Faturadas:</div>
                        <div class="card-row-value text-blue">R$ {{ number_format($accrualInflow, 2, ',', '.') }}</div>
                    </div>
                    <div class="card-row">
                        <div class="card-row-label">Total Despesas Lançadas:</div>
                        <div class="card-row-value" style="color: #64748b;">R$ {{ number_format($accrualOutflow, 2, ',', '.') }}</div>
                    </div>
                    <div class="card-row card-total" style="background-color: {{ $accrualBalance >= 0 ? '#eff6ff' : '#fff1f2' }}">
                        <div class="card-row-label">Resultado do Exercício:</div>
                        <div class="card-row-value @if($accrualBalance >= 0) text-blue @else text-red @endif">R$ {{ number_format($accrualBalance, 2, ',', '.') }}</div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Apuração Simples Nacional (RBT12) -->
    <div class="section-title">APURAÇÃO FISCAL E SIMPLES NACIONAL</div>
    <table class="grid" style="margin-bottom: 8px;">
        <tr>
            <td style="width: 25%; padding-right: 8px;">
                <div class="card" style="background-color: #f5f3ff; border-color: #ddd6fe; padding: 10px;">
                    <div class="card-title" style="font-size: 10px; color: #6d28d9;">RBT12 Acumulado</div>
                    <div class="text-bold text-violet font-mono" style="font-size: 13px;">R$ {{ number_format($rbt12Total, 2, ',', '.') }}</div>
                </div>
            </td>
            <td style="width: 25%; padding: 0 4px;">
                <div class="card" style="background-color: #f0fdf4; border-color: #bbf7d0; padding: 10px;">
                    <div class="card-title" style="font-size: 10px; color: #16a34a;">Alíquota Comércio</div>
                    <div class="text-bold text-green font-mono" style="font-size: 13px;">{{ number_format($comercioEffectiveRate, 2, ',', '.') }}%</div>
                </div>
            </td>
            <td style="width: 25%; padding: 0 4px;">
                <div class="card" style="background-color: #eff6ff; border-color: #bfdbfe; padding: 10px;">
                    <div class="card-title" style="font-size: 10px; color: #2563eb;">Alíquota Serviços</div>
                    <div class="text-bold text-blue font-mono" style="font-size: 13px;">{{ number_format($servicosEffectiveRate, 2, ',', '.') }}%</div>
                </div>
            </td>
            <td style="width: 25%; padding-left: 8px;">
                <div class="card" style="background-color: #fffbeb; border-color: #fde68a; padding: 10px;">
                    <div class="card-title" style="font-size: 10px; color: #d97706;">Provisão de Imposto</div>
                    <div class="text-bold text-red font-mono" style="font-size: 13px;">R$ {{ number_format($totalTaxDue, 2, ',', '.') }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th>Tipo de Receita</th>
                <th>Enquadramento</th>
                <th class="text-right">Faturamento Mês</th>
                <th class="text-right">Alíquota Efetiva</th>
                <th class="text-right">Provisão de Imposto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-bold">Comércio (Vendas)</td>
                <td>Anexo I do Simples Nacional</td>
                <td class="text-right font-mono">R$ {{ number_format($revenueComercio, 2, ',', '.') }}</td>
                <td class="text-right font-mono">{{ number_format($comercioEffectiveRate, 2, ',', '.') }}%</td>
                <td class="text-right font-mono text-green text-bold">R$ {{ number_format($taxComercio, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="text-bold">Serviços (Ordens de Serviço)</td>
                <td>Anexo III do Simples Nacional</td>
                <td class="text-right font-mono">R$ {{ number_format($revenueServicos, 2, ',', '.') }}</td>
                <td class="text-right font-mono">{{ number_format($servicosEffectiveRate, 2, ',', '.') }}%</td>
                <td class="text-right font-mono text-blue text-bold">R$ {{ number_format($revenueServicos * ($servicosEffectiveRate / 100), 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="text-bold">Receitas Avulsas</td>
                <td>Anexo III do Simples Nacional</td>
                <td class="text-right font-mono">R$ {{ number_format($revenueAvulsa, 2, ',', '.') }}</td>
                <td class="text-right font-mono">{{ number_format($servicosEffectiveRate, 2, ',', '.') }}%</td>
                <td class="text-right font-mono text-bold">R$ {{ number_format($revenueAvulsa * ($servicosEffectiveRate / 100), 2, ',', '.') }}</td>
            </tr>
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="2">Consolidação Fiscal</td>
                <td class="text-right font-mono">R$ {{ number_format($totalRevenueMonth, 2, ',', '.') }}</td>
                <td class="text-right">-</td>
                <td class="text-right font-mono text-red">R$ {{ number_format($totalTaxDue, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 40px; text-align: center; font-size: 10px; color: #94a3b8;">
        Este documento é uma consolidação financeira interna da empresa e não substitui o DAS oficial emitido pela Receita Federal.
    </div>

</body>
</html>
