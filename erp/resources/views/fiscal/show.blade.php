@extends('layouts.app')
@section('title', 'Resumo Fiscal — ' . $serviceOrder->code)

@section('topbar-actions')
    <a href="{{ route('service-orders.pdf', $serviceOrder) }}" class="btn btn-primary btn-sm flex items-center gap-1"><x-heroicon-o-document-arrow-down class="w-4 h-4"/> Baixar PDF</a>
    <a href="{{ route('service-orders.show', $serviceOrder) }}" class="btn btn-secondary btn-sm">← OS</a>
@endsection

@section('content')
<div style="max-width:800px;margin:0 auto">
    <div class="card" style="border:2px solid #e2e8f0">
        {{-- Cabeçalho --}}
        <div style="display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:20px;border-bottom:2px solid #f1f5f9;margin-bottom:20px">
            <div>
                <div class="flex items-center gap-2" style="font-size:22px;font-weight:800;color:#0f172a"><x-heroicon-o-receipt-percent class="w-6 h-6 text-indigo-600"/> Resumo Fiscal</div>
                <div style="font-size:14px;color:#64748b;margin-top:4px">Gerado em {{ $fiscal['generated_at'] }}</div>
            </div>
            <div style="text-align:right">
                <div style="font-size:20px;font-weight:800;color:var(--primary)">{{ $fiscal['os_code'] }}</div>
                <div style="font-size:13px;color:#64748b">Data: {{ $fiscal['os_date'] }}</div>
            </div>
        </div>

        {{-- Dados do Cliente --}}
        <div class="grid-2 mb-4">
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:8px">Tomador do Serviço</div>
                <div style="font-size:15px;font-weight:700">{{ $fiscal['client']['name'] }}</div>
                <div style="font-size:13px;color:#475569;margin-top:4px">{{ $fiscal['client']['document'] }}</div>
                <div style="font-size:13px;color:#475569">{{ $fiscal['client']['phone'] }}</div>
                <div style="font-size:13px;color:#475569">{{ $fiscal['client']['email'] }}</div>
                <div style="font-size:13px;color:#475569;margin-top:4px">{{ $fiscal['client']['address'] }}</div>
            </div>
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:8px">Prestador / Técnico</div>
                <div style="font-size:15px;font-weight:700">{{ $fiscal['technician']['name'] }}</div>
            </div>
        </div>

        {{-- Serviços --}}
        @if(!empty($fiscal['services']))
        <div class="mb-4">
            <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:8px">Serviços Prestados</div>
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:#f8fafc">
                        <th style="text-align:left;padding:10px 12px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0">Descrição</th>
                        <th style="text-align:center;padding:10px 12px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0">Qtd</th>
                        <th style="text-align:right;padding:10px 12px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0">Unitário</th>
                        <th style="text-align:right;padding:10px 12px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fiscal['services'] as $s)
                    <tr>
                        <td style="padding:10px 12px;font-size:13px;border-bottom:1px solid #f1f5f9">{{ $s['description'] }}</td>
                        <td style="padding:10px 12px;font-size:13px;text-align:center;border-bottom:1px solid #f1f5f9">{{ $s['quantity'] }} {{ $s['unit'] }}</td>
                        <td style="padding:10px 12px;font-size:13px;text-align:right;border-bottom:1px solid #f1f5f9">R$ {{ number_format($s['unit_price'], 2, ',', '.') }}</td>
                        <td style="padding:10px 12px;font-size:13px;font-weight:600;text-align:right;border-bottom:1px solid #f1f5f9">R$ {{ number_format($s['total'], 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Peças --}}
        @if(!empty($fiscal['parts']))
        <div class="mb-4">
            <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:8px">Peças / Materiais</div>
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:#f8fafc">
                        <th style="text-align:left;padding:10px 12px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0">Descrição</th>
                        <th style="text-align:center;padding:10px 12px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0">Cód</th>
                        <th style="text-align:center;padding:10px 12px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0">Qtd</th>
                        <th style="text-align:right;padding:10px 12px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fiscal['parts'] as $p)
                    <tr>
                        <td style="padding:10px 12px;font-size:13px;border-bottom:1px solid #f1f5f9">{{ $p['description'] }}</td>
                        <td style="padding:10px 12px;font-size:13px;text-align:center;border-bottom:1px solid #f1f5f9;color:#64748b">{{ $p['product_code'] ?? '—' }}</td>
                        <td style="padding:10px 12px;font-size:13px;text-align:center;border-bottom:1px solid #f1f5f9">{{ $p['quantity'] }} {{ $p['unit'] }}</td>
                        <td style="padding:10px 12px;font-size:13px;font-weight:600;text-align:right;border-bottom:1px solid #f1f5f9">R$ {{ number_format($p['total'], 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Totais --}}
        <div style="background:#f8fafc;border-radius:10px;padding:16px 20px;margin-top:8px">
            <div class="flex justify-between text-sm mb-2">
                <span class="text-muted">Mão de Obra</span>
                <strong>R$ {{ number_format($fiscal['totals']['services'], 2, ',', '.') }}</strong>
            </div>
            <div class="flex justify-between text-sm mb-3">
                <span class="text-muted">Peças / Materiais</span>
                <strong>R$ {{ number_format($fiscal['totals']['parts'], 2, ',', '.') }}</strong>
            </div>
            <div style="border-top:2px solid #e2e8f0;padding-top:12px;display:flex;justify-content:space-between;align-items:center">
                <span style="font-size:15px;font-weight:700">VALOR TOTAL</span>
                <span style="font-size:24px;font-weight:800;color:var(--primary)">R$ {{ number_format($fiscal['totals']['total'], 2, ',', '.') }}</span>
            </div>
        </div>

        @if($fiscal['notes'])
        <div style="margin-top:16px;padding:14px;background:#fffbeb;border-radius:8px;border:1px solid #fde68a">
            <div style="font-size:12px;font-weight:700;color:#92400e;margin-bottom:4px">Observações</div>
            <p style="font-size:13px;color:#78350f">{{ $fiscal['notes'] }}</p>
        </div>
        @endif

        {{-- WhatsApp --}}
        @php
        $waMsg = urlencode("*Resumo OS {$fiscal['os_code']}*\nCliente: {$fiscal['client']['name']}\nData: {$fiscal['os_date']}\nTotal: R$ " . number_format($fiscal['totals']['total'], 2, ',', '.') . "\n\nNeksa ERP");
        $waLink = "https://wa.me/?text={$waMsg}";
        @endphp
        <div class="flex gap-3 mt-4">
            <a href="{{ route('service-orders.pdf', $serviceOrder) }}" class="btn btn-primary flex items-center gap-2"><x-heroicon-o-document-arrow-down class="w-5 h-5"/> Baixar PDF</a>
            <a href="{{ $waLink }}" target="_blank" class="btn btn-success flex items-center gap-2">Enviar WhatsApp</a>
        </div>
    </div>
</div>
@endsection
