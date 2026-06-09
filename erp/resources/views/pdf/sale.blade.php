<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
@php
    $company = \App\Models\Company::first();
    $primaryColor = $company?->primary_color ?: '#4f46e5';
@endphp
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; padding: 30px; }
    .header-table { width: 100%; border-bottom: 2px solid {{ $primaryColor }}; padding-bottom: 12px; margin-bottom: 20px; }
    .header-logo { font-size: 16px; font-weight: bold; color: #0f172a; }
    .header-title { font-size: 14px; font-weight: bold; color: #475569; text-align: right; }
    .header-subtitle { font-size: 11px; color: #64748b; text-align: right; }
    
    .details-table { width: 100%; margin-bottom: 20px; }
    .details-table td { vertical-align: top; padding: 4px 0; }
    
    .section-title { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .08em; color: {{ $primaryColor }}; margin-top: 15px; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
    
    .label { font-size: 9px; color: #94a3b8; font-weight: bold; text-transform: uppercase; }
    .value { font-size: 11px; font-weight: 600; color: #1e293b; margin-bottom: 8px; }
    
    table.items-table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
    table.items-table th { background: #f8fafc; padding: 8px 10px; text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0; }
    table.items-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; }
    
    .total-row { font-weight: bold; }
    .total-row td { border-top: 1px solid #cbd5e1; padding: 6px 10px; }
    .grand-total { font-size: 13px; font-weight: 800; color: {{ $primaryColor }}; }
    
    .footer { position: fixed; bottom: 0; left: 30px; right: 30px; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
</style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="vertical-align: middle;">
            @if($company && $company->logo_path && file_exists(public_path('storage/' . $company->logo_path)))
                <img src="{{ public_path('storage/' . $company->logo_path) }}" style="max-height: 45px; max-width: 140px; margin-bottom: 6px;"><br>
            @endif
            <div class="header-logo">{{ $company?->name ?? 'Neksa ERP' }}</div>
            <div style="font-size: 9px; color: #475569; margin-top: 2px; line-height: 1.3;">
                {{ $company?->document ? 'CNPJ: ' . $company->document : '' }}
                {{ $company?->phone ? ' | Tel: ' . $company->phone : '' }}
                {{ $company?->email ? ' | Email: ' . $company->email : '' }}
                @if($company?->address)<br>{{ $company->address }}@endif
            </div>
        </td>
        <td style="text-align: right; vertical-align: middle;">
            <div class="header-title">Recibo de Venda Comercial: {{ $sale->code }}</div>
            <div class="header-subtitle" style="margin-top: 4px;">
                Status: <strong>{{ $sale->status->label() }}</strong><br>
                Emissão: {{ $sale->created_at->format('d/m/Y') }}<br>
                @if($sale->quote) Orçamento Origem: {{ $sale->quote->code }} @endif
            </div>
        </td>
    </tr>
</table>

<table class="details-table">
    <tr>
        <td style="width: 50%;">
            <div class="section-title">Cliente / Comprador</div>
            <div class="value" style="font-size: 12px; color: #0f172a;">{{ $sale->client->name }}</div>
            <div style="color: #475569; line-height: 1.3;">
                Documento: {{ $sale->client->formatted_document }}<br>
                Telefone: {{ $sale->client->phone ?? '—' }}<br>
                E-mail: {{ $sale->client->email ?? '—' }}
            </div>
        </td>
        <td style="width: 50%; padding-left: 20px;">
            @if($sale->clientAddress)
                <div class="section-title">Local de Entrega / Faturamento</div>
                <div class="value" style="font-size: 10px; color: #334155; line-height: 1.4;">
                    {{ $sale->clientAddress->full_address }}
                </div>
            @endif
        </td>
    </tr>
</table>

@if($sale->items->isNotEmpty())
    <div class="section-title">Produtos Adquiridos</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50%;">Descrição</th>
                <th style="width: 15%;">Tipo</th>
                <th style="width: 10%;">Qtd</th>
                <th style="width: 12%;">Preço Unit.</th>
                <th style="width: 13%; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>Produto</td>
                    <td>{{ (float) $item->quantity }} {{ $item->unit }}</td>
                    <td>R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                    <td style="text-align: right; font-weight: 600;">R$ {{ number_format($item->total_price, 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4" style="text-align: right;">Subtotal dos Itens:</td>
                <td style="text-align: right;">R$ {{ number_format($sale->items_amount, 2, ',', '.') }}</td>
            </tr>
            @if($sale->discount_amount > 0)
                <tr class="total-row" style="color: #dc2626;">
                    <td colspan="4" style="text-align: right;">Desconto Aplicado:</td>
                    <td style="text-align: right;">- R$ {{ number_format($sale->discount_amount, 2, ',', '.') }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td colspan="4" style="text-align: right; font-size: 11px;">Valor Total Final:</td>
                <td style="text-align: right;" class="grand-total">R$ {{ number_format($sale->total_amount, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
@endif

<div class="footer">
    Documento emitido por {{ $company?->name ?? 'Neksa ERP' }} em {{ now()->format('d/m/Y H:i') }} · Venda {{ $sale->code }}
</div>

</body>
</html>
