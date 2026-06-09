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
    .status-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 9px; font-weight: bold; background: #dbeafe; color: #1d4ed8; }
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
            <div class="header-title">Ordem de Serviço: {{ $serviceOrder->code }}</div>
            <div class="header-subtitle" style="margin-top: 4px;">
                Status: <strong>{{ $serviceOrder->status->name }}</strong><br>
                Criada em: {{ $serviceOrder->created_at->format('d/m/Y') }}<br>
                @if($serviceOrder->completed_at) Finalizada em: {{ $serviceOrder->completed_at->format('d/m/Y') }} @endif
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
                Documento: {{ $fiscal['client']['document'] ?? $serviceOrder->client->formatted_document }}<br>
                Telefone: {{ $serviceOrder->client->phone ?? '—' }}<br>
                E-mail: {{ $serviceOrder->client->email ?? '—' }}
            </div>
            <div style="color: #475569; margin-top: 4px; font-size: 9px; line-height: 1.3;">
                {{ $fiscal['client']['address'] ?? ($serviceOrder->clientAddress ? $serviceOrder->clientAddress->full_address : '') }}
            </div>
        </td>
        <td style="width: 50%; padding-left: 20px;">
            <div class="section-title">Informações de Execução</div>
            <div class="label">Técnico Responsável</div>
            <div class="value">{{ $serviceOrder->technician?->name ?? '—' }}</div>
            
            @if($serviceOrder->scheduled_at)
                <div class="label">Agendado Para</div>
                <div class="value">{{ $serviceOrder->scheduled_at->format('d/m/Y H:i') }}</div>
            @endif
            
            @if($serviceOrder->started_at)
                <div class="label">Início do Atendimento</div>
                <div class="value">{{ $serviceOrder->started_at->format('d/m/Y H:i') }}</div>
            @endif
        </td>
    </tr>
</table>

<div class="section-title">Descrição / Problema Relatado</div>
<p style="line-height: 1.5; margin-bottom: 15px; text-align: justify; color: #334155;">{{ $serviceOrder->description }}</p>

@if($serviceOrder->services_performed)
    <div class="section-title">Serviços Executados</div>
    <p style="line-height: 1.5; margin-bottom: 15px; text-align: justify; color: #334155;">{{ $serviceOrder->services_performed }}</p>
@endif

@if($serviceOrder->items->isNotEmpty())
    <div class="section-title">Itens / Mão de Obra / Peças</div>
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
                <td colspan="4" style="text-align: right; font-size: 11px;">Total Geral:</td>
                <td style="text-align: right;" class="grand-total">R$ {{ number_format($serviceOrder->total_amount, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
@endif

{{-- Checklists preenchidos --}}
@if($serviceOrder->checklists && $serviceOrder->checklists->isNotEmpty())
    @foreach($serviceOrder->checklists->where('filled_at', '!=', null) as $checklist)
    <div class="section-title" style="page-break-before:auto">Checklist: {{ $checklist->template->name }}</div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:12px;font-size:9px">
        <thead>
            <tr>
                <th style="text-align:left;padding:5px 8px;background:#f8fafc;border-bottom:1px solid #e2e8f0;width:60%">Pergunta</th>
                <th style="text-align:left;padding:5px 8px;background:#f8fafc;border-bottom:1px solid #e2e8f0">Resposta</th>
            </tr>
        </thead>
        <tbody>
            @foreach($checklist->instancedQuestions as $q)
            <tr style="border-bottom:1px solid #f1f5f9">
                <td style="padding:5px 8px;color:#334155">
                    {{ $q->question_text }}
                    @if($q->is_required)<span style="color:#dc2626;font-size:9px"> *</span>@endif
                </td>
                <td style="padding:5px 8px;font-weight:600;color:#1e293b">
                    @if($q->answer)
                        @if($q->question_type === 'photo')
                            [Foto anexada]
                        @elseif($q->question_type === 'drawing')
                            [Desenho registrado]
                        @elseif($q->question_type === 'checkbox')
                            {{ $q->answer->answer_value === 'sim' ? 'Sim / OK' : 'Não' }}
                        @else
                            {{ $q->answer->answer_value ?? '—' }}
                        @endif
                    @else
                        <span style="color:#94a3b8;font-style:italic">Não respondida</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <p style="font-size:8px;color:#64748b;margin-bottom:8px">Preenchido em {{ $checklist->filled_at->format('d/m/Y H:i') }}</p>
    @endforeach
@endif

{{-- Check-in --}}
@if($serviceOrder->checkins && $serviceOrder->checkins->isNotEmpty())
<div class="section-title">Registro de Check-in em Campo</div>
<table style="width:100%;border-collapse:collapse;margin-bottom:12px;font-size:9px">
    <thead>
        <tr>
            <th style="text-align:left;padding:5px 8px;background:#f8fafc;border-bottom:1px solid #e2e8f0">Técnico</th>
            <th style="text-align:left;padding:5px 8px;background:#f8fafc;border-bottom:1px solid #e2e8f0">Data/Hora</th>
            <th style="text-align:left;padding:5px 8px;background:#f8fafc;border-bottom:1px solid #e2e8f0">Coordenadas GPS</th>
        </tr>
    </thead>
    <tbody>
        @foreach($serviceOrder->checkins as $ci)
        <tr style="border-bottom:1px solid #f1f5f9">
            <td style="padding:5px 8px">{{ $ci->user->name }}</td>
            <td style="padding:5px 8px">{{ $ci->checked_at->format('d/m/Y H:i') }}</td>
            <td style="padding:5px 8px;color:#64748b">
                @if($ci->latitude) {{ number_format($ci->latitude,6) }}, {{ number_format($ci->longitude,6) }} @else — @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Fotos --}}
@if($serviceOrder->attachments && $serviceOrder->attachments->isNotEmpty())
<div class="section-title" style="page-break-before:always">Registros Fotográficos</div>
<table style="width:100%;border-collapse:collapse">
    <tr>
        @foreach($serviceOrder->attachments->take(6) as $i => $att)
        @php $path = storage_path('app/public/' . $att->path); @endphp
        @if(file_exists($path))
        <td style="width:33%;padding:4px;vertical-align:top">
            <img src="{{ $path }}" style="width:100%;height:100px;object-fit:cover;border-radius:4px;border:1px solid #e2e8f0">
            @if($att->caption)<div style="font-size:8px;color:#64748b;text-align:center;margin-top:2px">{{ $att->caption }}</div>@endif
        </td>
        @if(($i+1) % 3 === 0 && $i < $serviceOrder->attachments->count()-1)</tr><tr>@endif
        @endif
        @endforeach
    </tr>
</table>
@endif

@if($serviceOrder->signature)
    <div class="section-title" style="page-break-inside: avoid;">Assinatura do Cliente</div>
    <table style="width: 100%; page-break-inside: avoid; margin-top: 10px;">
        <tr>
            <td style="width: 60%; vertical-align: middle;">
                <p style="font-size: 10px; color: #334155; line-height: 1.4;">
                    Declaramos que os serviços descritos acima foram executados a contento.<br><br>
                    Assinado por: <strong>{{ $serviceOrder->signature->signer_name }}</strong><br>
                    Documento do Signatário: {{ $serviceOrder->signature->signer_document ?? 'Não informado' }}<br>
                    Data/Hora: {{ $serviceOrder->signature->signed_at->format('d/m/Y H:i') }}
                </p>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: middle;">
                @if(file_exists(storage_path('app/public/' . $serviceOrder->signature->path)))
                    <img src="{{ storage_path('app/public/' . $serviceOrder->signature->path) }}" style="max-width: 180px; max-height: 70px; border: 1px solid #cbd5e1; border-radius: 4px; background: white;">
                @else
                    <div style="border: 1px dashed #cbd5e1; padding: 15px; font-size: 9px; color: #94a3b8; text-align: center; border-radius: 4px;">Assinatura em arquivo</div>
                @endif
            </td>
        </tr>
    </table>
@endif

<div class="footer">
    Documento emitido por {{ $company?->name ?? 'Neksa ERP' }} em {{ now()->format('d/m/Y H:i') }} · OS {{ $serviceOrder->code }}
</div>

</body>
</html>
