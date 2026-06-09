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
            </div>
        </td>
        <td style="text-align: right; vertical-align: middle;">
            <div class="header-title">RECIBO DE ATENDIMENTO</div>
            <div class="header-subtitle" style="margin-top: 4px;">
                Cód OS: <strong>{{ $serviceOrder->code }}</strong><br>
                Emissão: {{ $serviceOrder->completed_at?->format('d/m/Y') ?? $serviceOrder->created_at->format('d/m/Y') }}<br>
                Status: {{ $serviceOrder->status->name }}
            </div>
        </td>
    </tr>
</table>

<table class="details-table">
    <tr>
        <td style="width: 50%;">
            <div class="section-title">Cliente</div>
            <div class="value" style="font-size: 12px; color: #0f172a;">{{ $serviceOrder->client->name }}</div>
            <div style="color: #475569; line-height: 1.3;">
                Documento: {{ $serviceOrder->client->formatted_document }}<br>
                Telefone: {{ $serviceOrder->client->phone ?? '—' }}<br>
                E-mail: {{ $serviceOrder->client->email ?? '—' }}
            </div>
        </td>
        <td style="width: 50%; padding-left: 20px;">
            @if($serviceOrder->clientAddress)
                <div class="section-title">Endereço de Atendimento</div>
                <div class="value" style="font-size: 10px; color: #334155; line-height: 1.4;">
                    {{ $serviceOrder->clientAddress->full_address }}
                </div>
            @endif
        </td>
    </tr>
</table>

<div class="section-title">Resumo do Atendimento</div>
<p style="line-height: 1.5; margin-bottom: 12px; text-align: justify; color: #334155;">
    {{ $serviceOrder->services_performed ?: $serviceOrder->description }}
</p>

@if($serviceOrder->items->isNotEmpty())
    <div class="section-title">Detalhe dos Itens & Mão de Obra</div>
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
            @foreach($serviceOrder->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ ['service' => 'Serviço', 'part' => 'Peça', 'material' => 'Material'][$item->type] ?? $item->type }}</td>
                    <td>{{ (float) $item->quantity }} {{ $item->unit }}</td>
                    <td>R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                    <td style="text-align: right; font-weight: 600;">R$ {{ number_format($item->total_price, 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4" style="text-align: right;">Mão de Obra / Serviços:</td>
                <td style="text-align: right;">R$ {{ number_format($serviceOrder->service_amount, 2, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="4" style="text-align: right;">Materiais / Peças:</td>
                <td style="text-align: right;">R$ {{ number_format($serviceOrder->parts_amount, 2, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="4" style="text-align: right; font-size: 11px;">Total Cobrado:</td>
                <td style="text-align: right;" class="grand-total">R$ {{ number_format($serviceOrder->total_amount, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
@endif

@if($serviceOrder->signature)
    <div class="section-title" style="page-break-inside: avoid;">Confirmação de Recebimento / Assinatura</div>
    <table style="width: 100%; page-break-inside: avoid; margin-top: 10px;">
        <tr>
            <td style="width: 60%; vertical-align: middle;">
                <p style="font-size: 10px; color: #334155; line-height: 1.4;">
                    Declaramos o recebimento dos serviços e peças listados acima.<br><br>
                    Assinado por: <strong>{{ $serviceOrder->signature->signer_name }}</strong><br>
                    Data/Hora: {{ $serviceOrder->signature->signed_at->format('d/m/Y H:i') }}
                </p>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: middle;">
                @if(file_exists(storage_path('app/public/' . $serviceOrder->signature->path)))
                    <img src="{{ storage_path('app/public/' . $serviceOrder->signature->path) }}" style="max-width: 180px; max-height: 70px; border: 1px solid #cbd5e1; border-radius: 4px; background: white;">
                @else
                    <div style="border: 1px dashed #cbd5e1; padding: 15px; font-size: 9px; color: #94a3b8; text-align: center; border-radius: 4px;">Assinatura Digital</div>
                @endif
            </td>
        </tr>
    </table>
@endif

<div class="footer">
    Comprovante de Execução · Emitido por {{ $company?->name ?? 'Neksa ERP' }} em {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
