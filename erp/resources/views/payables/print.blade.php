<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante Financeiro — {{ $payable->code }}</title>
    <style>
        body {
            font-family: 'Inter', Arial, sans-serif;
            color: #1a1a1a;
            background: #ffffff;
            margin: 0;
            padding: 40px;
            font-size: 14px;
            line-height: 1.5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            padding: 40px;
            border-radius: 8px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .title {
            font-size: 24px;
            font-weight: 800;
            color: #4f46e5;
            text-transform: uppercase;
        }
        .meta-info {
            text-align: right;
        }
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            margin-top: 30px;
            margin-bottom: 15px;
            text-transform: uppercase;
            border-bottom: 1px solid #f3f4f6;
            padding-bottom: 5px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .field {
            margin-bottom: 10px;
        }
        .label {
            font-size: 11px;
            text-transform: uppercase;
            color: #6b7280;
            display: block;
            margin-bottom: 3px;
        }
        .value {
            font-weight: 600;
            color: #1f2937;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table th, .table td {
            border: 1px solid #e5e7eb;
            padding: 12px;
            text-align: left;
        }
        .table th {
            background-color: #f9fafb;
            color: #374151;
            font-weight: 700;
        }
        .totais {
            margin-top: 30px;
            border-top: 2px solid #e2e8f0;
            padding-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 40px;
        }
        .total-item {
            text-align: right;
        }
        .total-val {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
        }
        .signature-area {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            gap: 40px;
        }
        .signature-box {
            flex: 1;
            border-top: 1px solid #9ca3af;
            text-align: center;
            padding-top: 10px;
            margin-top: 40px;
            font-size: 12px;
            color: #4b5563;
        }
        @media print {
            body {
                padding: 0;
            }
            .container {
                border: none;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <div class="title">Autorização de Contas a Pagar</div>
                <div style="font-size:12px; color:#4b5563; margin-top:4px">Código do Documento: <strong>{{ $payable->code }}</strong></div>
            </div>
            <div class="meta-info">
                <div class="value" style="font-size: 16px; color: #4f46e5">R$ {{ number_format($payable->net_amount, 2, ',', '.') }}</div>
                <div class="label" style="margin-top:4px">Status: {{ $payable->status->label() }}</div>
            </div>
        </div>

        <div class="section-title">Dados do Fornecedor</div>
        <div class="grid">
            <div class="field">
                <span class="label">Fornecedor / Favorecido</span>
                <span class="value">{{ $payable->supplier->name ?? 'Fornecedor Avulso' }}</span>
            </div>
            <div class="field">
                <span class="label">CPF / CNPJ</span>
                <span class="value">{{ $payable->supplier->document ?? '-' }}</span>
            </div>
            <div class="field">
                <span class="label">E-mail</span>
                <span class="value">{{ $payable->supplier->email ?? '-' }}</span>
            </div>
            <div class="field">
                <span class="label">Telefone</span>
                <span class="value">{{ $payable->supplier->phone ?? '-' }}</span>
            </div>
        </div>

        <div class="section-title">Informações do Título</div>
        <div class="field" style="margin-bottom: 20px">
            <span class="label">Descrição / Histórico</span>
            <span class="value" style="font-size: 15px">{{ $payable->description }}</span>
        </div>
        <div class="grid">
            <div class="field">
                <span class="label">Data de Competência</span>
                <span class="value">{{ \Carbon\Carbon::parse($payable->competence_date)->format('d/m/Y') }}</span>
            </div>
            <div class="field">
                <span class="label">Data de Emissão</span>
                <span class="value">{{ $payable->created_at->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        <div class="section-title">Detalhamento das Parcelas</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Parcela</th>
                    <th>Vencimento</th>
                    <th>Valor</th>
                    <th>Juros</th>
                    <th>Desconto</th>
                    <th>Valor Pago</th>
                    <th>Data Pagamento</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payable->installments as $inst)
                    <tr>
                        <td>{{ $inst->installment_number }}</td>
                        <td>{{ \Carbon\Carbon::parse($inst->due_date)->format('d/m/Y') }}</td>
                        <td>R$ {{ number_format($inst->amount, 2, ',', '.') }}</td>
                        <td>R$ {{ number_format($inst->interest_amount, 2, ',', '.') }}</td>
                        <td>R$ {{ number_format($inst->discount_amount, 2, ',', '.') }}</td>
                        <td>R$ {{ number_format($inst->paid_amount, 2, ',', '.') }}</td>
                        <td>{{ $inst->paid_at ? \Carbon\Carbon::parse($inst->paid_at)->format('d/m/Y') : '-' }}</td>
                        <td>{{ $inst->status->label() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totais">
            <div class="total-item">
                <span class="label">Valor Original</span>
                <span class="value">R$ {{ number_format($payable->total_amount, 2, ',', '.') }}</span>
            </div>
            <div class="total-item">
                <span class="label">Descontos obtidos</span>
                <span class="value" style="color: #10b981">R$ {{ number_format($payable->discount_amount, 2, ',', '.') }}</span>
            </div>
            <div class="total-item">
                <span class="label">Juros pagos</span>
                <span class="value" style="color: #f59e0b">R$ {{ number_format($payable->interest_amount, 2, ',', '.') }}</span>
            </div>
            <div class="total-item">
                <span class="label">Valor Líquido</span>
                <span class="total-val">R$ {{ number_format($payable->net_amount, 2, ',', '.') }}</span>
            </div>
        </div>

        <div class="signature-area">
            <div class="signature-box">
                Neksa ERP<br>Gestor Financeiro / Responsável
            </div>
            <div class="signature-box">
                {{ $payable->supplier->name ?? 'Fornecedor Avulso' }}<br>Assinatura do Favorecido / Quitação
            </div>
        </div>
    </div>

    <div class="no-print" style="margin-top: 30px; text-align: center">
        <button onclick="window.print()" style="padding: 10px 20px; background-color: #4f46e5; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600">Imprimir Documento</button>
    </div>
</body>
</html>
