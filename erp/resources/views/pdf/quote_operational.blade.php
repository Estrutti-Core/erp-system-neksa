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
            <div class="header-title">FICHA DE CAMPO / OPERACIONAL</div>
            <div class="header-subtitle" style="margin-top: 4px;">
                Cód Orçamento: <strong>{{ $quote->code }}</strong><br>
                Emissão: {{ $quote->created_at->format('d/m/Y') }}<br>
                Status: {{ $quote->status->label() }}
            </div>
        </td>
    </tr>
</table>

<table class="details-table">
    <tr>
        <td style="width: 50%;">
            <div class="section-title">Cliente</div>
            <div class="value" style="font-size: 12px; color: #0f172a;">{{ $quote->client->name }}</div>
            <div style="color: #475569; line-height: 1.3;">
                Telefone: {{ $quote->client->phone ?? '—' }}<br>
                E-mail: {{ $quote->client->email ?? '—' }}
            </div>
        </td>
        <td style="width: 50%; padding-left: 20px;">
            @if($quote->clientAddress)
                <div class="section-title">Local de Atendimento / Entrega</div>
                <div class="value" style="font-size: 10px; color: #334155; line-height: 1.4;">
                    {{ $quote->clientAddress->full_address }}
                </div>
            @endif
        </td>
    </tr>
</table>

@if($quote->equipment)
    <div class="section-title">Equipamento para Atendimento</div>
    <table style="width: 100%; font-size: 9px; margin-bottom: 15px; border: 1px solid #e2e8f0; padding: 10px; border-radius: 4px; background-color: #f8fafc;">
        <tr>
            <td style="width: 33%;">
                <span class="label">Nome/Modelo:</span><br>
                <strong>{{ $quote->equipment->name }}</strong>
            </td>
            <td style="width: 33%;">
                <span class="label">Nº Série:</span><br>
                <strong>{{ $quote->equipment->serial_number ?: 'N/A' }}</strong>
            </td>
            <td style="width: 33%;">
                <span class="label">Marca:</span><br>
                <strong>{{ $quote->equipment->brand ?: 'N/A' }}</strong>
            </td>
        </tr>
    </table>
@endif

@if($quote->items->isNotEmpty())
    <div class="section-title">Serviços & Produtos Planejados</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 70%;">Descrição</th>
                <th style="width: 15%;">Tipo</th>
                <th style="width: 15%;">Qtd Planejada</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->type->label() }}</td>
                    <td style="font-weight: 600;">{{ (float) $item->quantity }} {{ $item->unit }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($quote->carrier || $quote->delivery_deadline || $quote->warranty)
    <div class="section-title" style="page-break-inside: avoid;">Logística & Prazos</div>
    <table style="width: 100%; font-size: 9px; margin-bottom: 20px; page-break-inside: avoid;">
        <tr>
            <td style="width: 33%; padding: 4px 0;">
                <span class="label">Transportadora:</span><br>
                <strong>{{ $quote->carrier ?: 'Não informada' }}</strong>
            </td>
            <td style="width: 33%; padding: 4px 0;">
                <span class="label">Modalidade do Frete:</span><br>
                <strong>
                    @if($quote->freight_type === 0) CIF (Remetente)
                    @elseif($quote->freight_type === 1) FOB (Destinatário)
                    @elseif($quote->freight_type === 2) Terceiros
                    @elseif($quote->freight_type === 3) Remetente (Próprio)
                    @elseif($quote->freight_type === 4) Destinatário (Próprio)
                    @else Sem Frete
                    @endif
                </strong>
            </td>
            <td style="width: 33%; padding: 4px 0;">
                <span class="label">Volumes / Peso:</span><br>
                <strong>
                    {{ $quote->volume ?: '—' }} vol
                    @if($quote->weight_gross)
                        / {{ number_format($quote->weight_gross, 2, ',', '.') }} kg
                    @endif
                </strong>
            </td>
        </tr>
        <tr>
            <td style="padding: 4px 0;">
                <span class="label">Prazo de Entrega:</span><br>
                <strong>{{ $quote->delivery_deadline ?: '—' }}</strong>
            </td>
            <td style="padding: 4px 0;">
                <span class="label">Garantia Comercial:</span><br>
                <strong>{{ $quote->warranty ?: '—' }}</strong>
            </td>
            <td style="padding: 4px 0;">
            </td>
        </tr>
    </table>
@endif

@if($quote->notes)
    <div class="section-title" style="page-break-inside: avoid;">Instruções Operacionais / Observações</div>
    <p style="line-height: 1.5; text-align: justify; color: #475569; font-size: 9px; page-break-inside: avoid;">{!! nl2br(e($quote->notes)) !!}</p>
@endif

<div class="footer">
    Documento Operacional de Uso Interno · Emitido por {{ $company?->name ?? 'Neksa ERP' }} em {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
