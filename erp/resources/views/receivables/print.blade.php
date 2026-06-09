<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recibo Financeiro — {{ $receivable->code }}</title>
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
                <div class="title">Recibo de Contas a Receber</div>
                <div style="font-size:12px; color:#4b5563; margin-top:4px">Código do Documento: <strong>{{ $receivable->code }}</strong></div>
            </div>
            <div class="meta-info">
                <div class="value" style="font-size: 16px; color: #4f46e5">R$ {{ number_format($receivable->net_amount, 2, ',', '.') }}</div>
                <div class="label" style="margin-top:4px">Status: {{ $receivable->status->label() }}</div>
            </div>
        </div>

        <div class="section-title">Dados do Cliente</div>
        <div class="grid">
            <div class="field">
                <span class="label">Nome / Razão Social</span>
                <span class="value">{{ $receivable->client->name ?? 'Cliente Avulso' }}</span>
            </div>
            <div class="field">
                <span class="label">CPF / CNPJ</span>
                <span class="value">{{ $receivable->client->document ?? '-' }}</span>
            </div>
            <div class="field">
                <span class="label">E-mail</span>
                <span class="value">{{ $receivable->client->email ?? '-' }}</span>
            </div>
            <div class="field">
                <span class="label">Telefone</span>
                <span class="value">{{ $receivable->client->phone ?? '-' }}</span>
            </div>
        </div>

        <div class="section-title">Informações do Título</div>
        <div class="field" style="margin-bottom: 20px">
            <span class="label">Descrição / Histórico</span>
            <span class="value" style="font-size: 15px">{{ $receivable->description }}</span>
        </div>
        <div class="grid">
            <div class="field">
                <span class="label">Data de Competência</span>
                <span class="value">{{ \Carbon\Carbon::parse($receivable->competence_date)->format('d/m/Y') }}</span>
            </div>
            <div class="field">
                <span class="label">Data de Emissão</span>
                <span class="value">{{ $receivable->created_at->format('d/m/Y H:i') }}</span>
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
                @foreach($receivable->installments as $inst)
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
                <span class="value">R$ {{ number_format($receivable->total_amount, 2, ',', '.') }}</span>
            </div>
            <div class="total-item">
                <span class="label">Descontos</span>
                <span class="value" style="color: #10b981">R$ {{ number_format($receivable->discount_amount, 2, ',', '.') }}</span>
            </div>
            <div class="total-item">
                <span class="label">Juros / Multas</span>
                <span class="value" style="color: #f59e0b">R$ {{ number_format($receivable->interest_amount, 2, ',', '.') }}</span>
            </div>
            <div class="total-item">
                <span class="label">Valor Líquido</span>
                <span class="total-val">R$ {{ number_format($receivable->net_amount, 2, ',', '.') }}</span>
            </div>
        </div>

        <div class="signature-area">
            <div class="signature-box">
                Neksa ERP<br>Emissor Autorizado
            </div>
            <div class="signature-box">
                {{ $receivable->client->name ?? 'Cliente Avulso' }}<br>Cliente / Pagador
            </div>
        </div>
    </div>

    <div class="no-print" style="margin-top: 30px; text-align: center">
        <button onclick="window.print()" style="padding: 10px 20px; background-color: #4f46e5; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600">Imprimir Documento</button>
    </div>
</body>
</html>
