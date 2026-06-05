@extends('layouts.app')
@section('title', $serviceOrder->code)

@section('topbar-actions')
    <form action="{{ route('service-orders.duplicate', $serviceOrder) }}" method="POST" style="display:inline;" onsubmit="return confirm('Duplicar esta OS?')">
        @csrf
        <button type="submit" class="btn btn-secondary btn-sm">Duplicar</button>
    </form>
    <a href="{{ route('service-orders.pdf', $serviceOrder) }}" class="btn btn-secondary btn-sm">PDF</a>
    <a href="{{ route('service-orders.fiscal', $serviceOrder) }}" class="btn btn-secondary btn-sm">Fiscal</a>
    @can('update', $serviceOrder)
        <a href="{{ route('service-orders.edit', $serviceOrder) }}" class="btn btn-primary btn-sm">Editar</a>
    @endcan
@endsection

@section('content')
<div class="flex items-center gap-3 mb-4">
    <a href="{{ route('service-orders.index') }}" class="btn btn-secondary btn-sm">← Voltar</a>
    <span class="badge badge-{{ $serviceOrder->status->color }}" style="font-size:13px;padding:5px 14px">{{ $serviceOrder->status->name }}</span>
    <span class="badge badge-{{ $serviceOrder->priority->color() }}">{{ $serviceOrder->priority->label() }}</span>
</div>

@if($errors->any())
<div class="card mb-4" style="border-left:4px solid #dc2626;background:#fef2f2;">
    @foreach($errors->all() as $error)
        <p style="margin:0;color:#dc2626;font-size:14px;font-weight:600;">{{ $error }}</p>
    @endforeach
</div>
@endif

@if(session('success'))
<div class="card mb-4" style="border-left:4px solid #16a34a;background:#f0fdf4;">
    <p style="margin:0;color:#16a34a;font-size:14px;font-weight:600;">{{ session('success') }}</p>
</div>
@endif

<div class="grid-2">
{{-- COLUNA PRINCIPAL --}}
<div>

{{-- Informações da OS --}}
<div class="card mb-4">
    <h2 class="font-bold mb-3 flex items-center gap-2" style="font-size:16px">
        <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-indigo-600"/> {{ $serviceOrder->code }}
    </h2>
    <div class="grid-2">
        <div>
            <div class="text-xs text-muted">Cliente</div>
            <div class="font-semibold mt-1">
                <a href="{{ route('clients.show', $serviceOrder->client) }}" style="color:var(--primary);text-decoration:none">{{ $serviceOrder->client->name }}</a>
            </div>
            <div class="text-sm text-muted">{{ $serviceOrder->client->phone }}</div>
        </div>
        <div>
            <div class="text-xs text-muted">Equipamento</div>
            <div class="font-semibold mt-1">
                @if($serviceOrder->equipment)
                    {{ $serviceOrder->equipment->name }}
                    @if($serviceOrder->equipment->serial_number)
                        <div class="text-xs text-muted font-normal">S/N: {{ $serviceOrder->equipment->serial_number }}</div>
                    @endif
                @else
                    <span class="text-muted font-normal">—</span>
                @endif
            </div>
        </div>
        <div>
            <div class="text-xs text-muted">Endereço</div>
            <div class="text-sm mt-1">{{ $serviceOrder->clientAddress?->full_address ?? '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-muted">Técnico</div>
            <div class="font-semibold mt-1">{{ $serviceOrder->technician?->name ?? 'Não atribuído' }}</div>
        </div>
        <div>
            <div class="text-xs text-muted">Criado por</div>
            <div class="text-sm mt-1">{{ $serviceOrder->creator->name }} · {{ $serviceOrder->created_at->format('d/m/Y H:i') }}</div>
        </div>
        @if($serviceOrder->scheduled_at)
        <div>
            <div class="text-xs text-muted">Agendado</div>
            <div class="font-semibold mt-1">{{ $serviceOrder->scheduled_at->format('d/m/Y H:i') }}</div>
        </div>
        @endif
        @if($serviceOrder->completed_at)
        <div>
            <div class="text-xs text-muted">Finalizado</div>
            <div class="font-semibold mt-1" style="color:#16a34a">{{ $serviceOrder->completed_at->format('d/m/Y H:i') }}</div>
        </div>
        @endif
    </div>
    <div class="border-t mt-3 pt-3">
        <div class="text-xs text-muted mb-1">Descrição do Problema</div>
        <p style="font-size:14px;line-height:1.6;margin:0">{{ $serviceOrder->description }}</p>
    </div>
    @if($serviceOrder->services_performed)
    <div class="mt-3">
        <div class="text-xs text-muted mb-1">Serviços Executados</div>
        <p style="font-size:14px;line-height:1.6;margin:0">{{ $serviceOrder->services_performed }}</p>
    </div>
    @endif
</div>

{{-- Check-in GPS --}}
<div class="card mb-4">
    <h3 class="font-bold mb-3 flex items-center gap-2">
        <x-heroicon-o-map-pin class="w-5 h-5 text-indigo-600"/> Check-in em Campo
    </h3>
    @if($serviceOrder->checkins->isNotEmpty())
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px;">
            @foreach($serviceOrder->checkins as $ci)
            <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;background:{{ $ci->type==='checkin'?'#f0fdf4':'#fef3c7' }};border:1px solid {{ $ci->type==='checkin'?'#86efac':'#fcd34d' }}">
                <span style="font-size:11px;font-weight:700;color:{{ $ci->type==='checkin'?'#16a34a':'#d97706' }};text-transform:uppercase">{{ $ci->type==='checkin'?'Chegada':'Saída' }}</span>
                <span style="font-size:13px;font-weight:600;color:#1e293b">{{ $ci->user->name }}</span>
                <span style="font-size:12px;color:#64748b;margin-left:auto">{{ $ci->checked_at->format('d/m/Y H:i') }}</span>
                @if($ci->latitude)
                <a href="https://maps.google.com/?q={{ $ci->latitude }},{{ $ci->longitude }}" target="_blank" style="font-size:11px;color:#6366f1">Ver mapa</a>
                @endif
            </div>
            @endforeach
        </div>
    @endif
    @can('update', $serviceOrder)
    @if(!$serviceOrder->isCompleted() && !$serviceOrder->isCancelled())
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" id="btn-checkin" class="btn btn-primary btn-sm" onclick="doCheckin('checkin')" style="flex:1;justify-content:center">
            Registrar Chegada
        </button>
        @if($serviceOrder->hasCheckin())
        <button type="button" id="btn-checkout" class="btn btn-secondary btn-sm" onclick="doCheckin('checkout')" style="flex:1;justify-content:center">
            Registrar Saída
        </button>
        @endif
    </div>
    <form id="checkin-form" method="POST" action="{{ route('service-orders.checkin', $serviceOrder) }}" style="display:none">
        @csrf
        <input type="hidden" name="type" id="checkin-type">
        <input type="hidden" name="latitude" id="checkin-lat">
        <input type="hidden" name="longitude" id="checkin-lng">
    </form>
    @endif
    @endcan
</div>

{{-- Checklists --}}
@if($serviceOrder->checklists->isNotEmpty())
<div class="card mb-4">
    <h3 class="font-bold mb-3 flex items-center gap-2">
        <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-indigo-600"/> Checklists Operacionais
    </h3>
    <div style="display:flex;flex-direction:column;gap:8px;">
        @foreach($serviceOrder->checklists as $checklist)
        @php
            $total = $checklist->instancedQuestions->count();
            $answered = $checklist->instancedQuestions->filter(fn($q) => $q->answer)->count();
            $pct = $total > 0 ? round(($answered/$total)*100) : 0;
        @endphp
        <div style="border:1px solid #e2e8f0;border-radius:10px;padding:14px;background:{{ $checklist->is_inactive?'#f8fafc':'#fff' }};opacity:{{ $checklist->is_inactive?'0.65':'1' }}">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <div style="flex:1">
                    <div class="font-semibold" style="font-size:14px;color:#1e293b">{{ $checklist->template->name }}</div>
                    @if($checklist->is_inactive)
                        <span style="font-size:11px;color:#64748b;font-style:italic">Inativo (serviço removido — evidência preservada)</span>
                    @elseif($checklist->isFilled())
                        <span style="font-size:11px;font-weight:600;color:#16a34a">Preenchido em {{ $checklist->filled_at->format('d/m H:i') }}</span>
                    @else
                        <span style="font-size:11px;color:#d97706;font-weight:600">Pendente — {{ $answered }}/{{ $total }} respostas</span>
                    @endif
                </div>
                @if(!$checklist->is_inactive)
                @can('update', $serviceOrder)
                <a href="{{ route('service-orders.checklists.fill', [$serviceOrder, $checklist]) }}"
                   class="btn btn-{{ $checklist->isFilled()?'secondary':'primary' }} btn-sm" style="white-space:nowrap">
                   {{ $checklist->isFilled()?'Rever':'Preencher' }}
                </a>
                @endcan
                @endif
            </div>
            @if($total > 0 && !$checklist->is_inactive)
            <div style="margin-top:8px;background:#e2e8f0;border-radius:99px;height:4px;overflow:hidden">
                <div style="height:4px;border-radius:99px;background:{{ $pct==100?'#16a34a':'#6366f1' }};width:{{ $pct }}%;transition:width 0.4s"></div>
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Itens --}}
@if($serviceOrder->items->isNotEmpty())
<div class="card mb-4">
    <h3 class="font-bold mb-3 flex items-center gap-2"><x-heroicon-o-cog-6-tooth class="w-5 h-5 text-indigo-600"/> Itens / Peças</h3>
    <table>
        <thead><tr><th>Descrição</th><th>Qtd</th><th>Unit.</th><th>Total</th></tr></thead>
        <tbody>
            @foreach($serviceOrder->items as $item)
            <tr>
                <td>
                    <div style="font-weight:500">{{ $item->description }}</div>
                    <div class="text-xs text-muted">{{ ['service'=>'Serviço','part'=>'Peça','material'=>'Material'][$item->type] ?? $item->type }}</div>
                </td>
                <td>{{ $item->quantity }} {{ $item->unit }}</td>
                <td>R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                <td class="font-semibold">R$ {{ number_format($item->total_price, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="border-t mt-3 pt-3 text-right">
        <div class="text-sm text-muted">Mão de obra: <strong>R$ {{ number_format($serviceOrder->service_amount, 2, ',', '.') }}</strong></div>
        <div class="text-sm text-muted">Peças: <strong>R$ {{ number_format($serviceOrder->parts_amount, 2, ',', '.') }}</strong></div>
        <div style="font-size:18px;font-weight:800;color:#0f172a;margin-top:4px">Total: R$ {{ number_format($serviceOrder->total_amount, 2, ',', '.') }}</div>
    </div>
</div>
@endif

{{-- Anexos --}}
<div class="card mb-4">
    <h3 class="font-bold mb-3 flex items-center gap-2"><x-heroicon-o-paper-clip class="w-5 h-5 text-indigo-600"/> Anexos</h3>
    @if($serviceOrder->attachments->isNotEmpty())
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:16px">
        @foreach($serviceOrder->attachments as $att)
        <div style="position:relative;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;background:#f8fafc">
            @if($att->isImage())
                <img src="{{ $att->url }}" alt="{{ $att->original_name }}" style="width:100%;aspect-ratio:1;object-fit:cover">
            @else
                <div style="width:100%;aspect-ratio:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:4px">
                    <x-heroicon-o-document class="w-8 h-8" style="color:#94a3b8"/>
                    <span style="font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase">{{ pathinfo($att->original_name,PATHINFO_EXTENSION) }}</span>
                </div>
            @endif
            <div style="padding:6px 8px">
                <div style="font-size:11px;color:#475569;overflow:hidden;white-space:nowrap;text-overflow:ellipsis" title="{{ $att->original_name }}">{{ $att->original_name }}</div>
                <div style="font-size:10px;color:#94a3b8">{{ $att->formatted_size }}</div>
            </div>
            <div style="display:flex;gap:4px;padding:0 6px 6px">
                <a href="{{ $att->url }}" target="_blank" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center;padding:4px;font-size:11px">Ver</a>
                @can('update', $serviceOrder)
                <form method="POST" action="{{ route('service-orders.attachments.destroy', [$serviceOrder, $att]) }}" onsubmit="return confirm('Remover anexo?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-secondary btn-sm" style="padding:4px 6px;color:#dc2626"><x-heroicon-o-trash class="w-3 h-3"/></button>
                </form>
                @endcan
            </div>
        </div>
        @endforeach
    </div>
    @endif
    @can('update', $serviceOrder)
    @if(!$serviceOrder->isCompleted() && !$serviceOrder->isCancelled())
    <form method="POST" action="{{ route('service-orders.attachments.store', $serviceOrder) }}" enctype="multipart/form-data">
        @csrf
        <label style="display:block;border:2px dashed #cbd5e1;border-radius:10px;padding:20px;text-align:center;cursor:pointer;color:#64748b;font-size:14px" id="upload-label">
            <x-heroicon-o-cloud-arrow-up class="w-7 h-7" style="margin:0 auto 6px;display:block;color:#94a3b8"/>
            Toque para selecionar fotos ou documentos
            <input type="file" name="attachments[]" multiple accept="image/*,application/pdf,video/*" style="display:none" id="upload-input">
        </label>
        <div id="upload-preview" style="display:none;margin-top:8px;font-size:13px;color:#475569;font-weight:600"></div>
        <button type="submit" class="btn btn-primary w-full mt-3" style="justify-content:center">Enviar Arquivos</button>
    </form>
    @endif
    @endcan
</div>

{{-- Assinatura --}}
<div class="card mb-4">
    <h3 class="font-bold mb-3 flex items-center gap-2"><x-heroicon-o-pencil class="w-5 h-5 text-indigo-600"/> Assinatura do Cliente</h3>
    @if($serviceOrder->signature)
    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:14px;margin-bottom:12px">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <img src="{{ $serviceOrder->signature->url }}" alt="Assinatura" style="height:60px;border:1px solid #e2e8f0;border-radius:8px;background:#fff">
            <div>
                <div class="font-semibold" style="color:#1e293b">{{ $serviceOrder->signature->signer_name }}</div>
                @if($serviceOrder->signature->signer_document)
                <div class="text-sm text-muted">{{ $serviceOrder->signature->signer_document }}</div>
                @endif
                <div class="text-xs text-muted">Coletada em {{ $serviceOrder->signature->signed_at->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </div>
    @endif
    @can('update', $serviceOrder)
    @if(!$serviceOrder->isCompleted() && !$serviceOrder->isCancelled())
    <div>
        <div class="form-group mb-3">
            <label class="form-label">Nome do Signatário <span style="color:#dc2626">*</span></label>
            <input type="text" id="sig-name" class="form-control" placeholder="Nome completo do cliente">
        </div>
        <div class="form-group mb-3">
            <label class="form-label">CPF / RG</label>
            <input type="text" id="sig-doc" class="form-control" placeholder="Documento (opcional)">
        </div>
        <label class="form-label mb-1">Assinatura <span style="color:#dc2626">*</span></label>
        <canvas id="sig-canvas" style="width:100%;border:2px solid #e2e8f0;border-radius:10px;background:#fff;touch-action:none;cursor:crosshair;display:block" height="160"></canvas>
        <div style="display:flex;gap:8px;margin-top:8px">
            <button type="button" onclick="clearSignature()" class="btn btn-secondary btn-sm">Limpar</button>
            <button type="button" onclick="saveSignature()" class="btn btn-primary btn-sm" style="flex:1;justify-content:center">Salvar Assinatura</button>
        </div>
        <form id="sig-form" method="POST" action="{{ route('service-orders.signature', $serviceOrder) }}" style="display:none">
            @csrf
            <input type="hidden" name="signer_name" id="sig-form-name">
            <input type="hidden" name="signer_document" id="sig-form-doc">
            <input type="hidden" name="signature_data" id="sig-form-data">
            <input type="hidden" name="latitude" id="sig-form-lat">
            <input type="hidden" name="longitude" id="sig-form-lng">
        </form>
    </div>
    @endif
    @endcan
</div>

</div>{{-- /coluna principal --}}

{{-- COLUNA LATERAL --}}
<div>

{{-- Status --}}
@can('changeStatus', $serviceOrder)
@if($allowedStatuses->isNotEmpty())
<div class="card mb-4">
    <h3 class="font-bold mb-3 flex items-center gap-2"><x-heroicon-o-arrow-path class="w-5 h-5 text-indigo-600"/> Atualizar Status</h3>
    <form method="POST" action="{{ route('service-orders.change-status', $serviceOrder) }}">
        @csrf
        <div class="form-group">
            <select name="status" class="form-control">
                @foreach($allowedStatuses as $status)
                    <option value="{{ $status->slug }}">{{ $status->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <textarea name="note" rows="2" class="form-control" placeholder="Observação (opcional)..."></textarea>
        </div>
        <button type="submit" class="btn btn-primary w-full" style="justify-content:center">Atualizar Status</button>
    </form>
</div>
@endif
@endcan

{{-- Técnico --}}
@can('assignTechnician', $serviceOrder)
@if(!$serviceOrder->isCompleted() && !$serviceOrder->isCancelled())
<div class="card mb-4">
    <h3 class="font-bold mb-3 flex items-center gap-2"><x-heroicon-o-user-plus class="w-5 h-5 text-indigo-600"/> Atribuir Técnico</h3>
    <form method="POST" action="{{ route('service-orders.update', $serviceOrder) }}">
        @csrf @method('PUT')
        <div class="form-group">
            <select name="technician_id" class="form-control">
                <option value="">Sem técnico</option>
                @foreach($technicians as $tech)
                    <option value="{{ $tech->id }}" {{ $serviceOrder->technician_id == $tech->id ? 'selected' : '' }}>{{ $tech->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-secondary w-full" style="justify-content:center">Salvar</button>
    </form>
</div>
@endif
@endcan

{{-- Histórico --}}
<div class="card">
    <h3 class="font-bold mb-4 flex items-center gap-2"><x-heroicon-o-clock class="w-5 h-5 text-indigo-600"/> Histórico</h3>
    <div class="timeline">
        @forelse($serviceOrder->history as $event)
        <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-content">
                <div class="timeline-title">{{ $event->event_label }}</div>
                @if($event->description)
                    <div style="font-size:12px;color:#475569;margin-top:2px">{{ $event->description }}</div>
                @endif
                <div class="timeline-meta">{{ $event->user?->name ?? 'Sistema' }} · {{ $event->created_at->format('d/m/Y H:i') }}</div>
            </div>
        </div>
        @empty
        <p class="text-sm text-muted">Sem eventos registrados.</p>
        @endforelse
    </div>
</div>

</div>{{-- /coluna lateral --}}
</div>{{-- /grid-2 --}}
@endsection

@push('scripts')
<script>
// ── Check-in GPS ──────────────────────────────────────────────────────────────
function doCheckin(type) {
    if (!navigator.geolocation) { alert('Geolocalização não suportada.'); return; }
    const btn = document.getElementById('btn-' + type);
    btn.textContent = 'Obtendo localização...';
    btn.disabled = true;
    navigator.geolocation.getCurrentPosition(pos => {
        document.getElementById('checkin-type').value = type;
        document.getElementById('checkin-lat').value  = pos.coords.latitude;
        document.getElementById('checkin-lng').value  = pos.coords.longitude;
        document.getElementById('checkin-form').submit();
    }, () => {
        // Sem GPS: enviar sem coordenadas
        document.getElementById('checkin-type').value = type;
        document.getElementById('checkin-form').submit();
    }, { enableHighAccuracy: true, timeout: 8000 });
}

// ── Canvas de Assinatura ──────────────────────────────────────────────────────
const canvas = document.getElementById('sig-canvas');
if (canvas) {
    const ctx = canvas.getContext('2d');
    let drawing = false, lastX = 0, lastY = 0;

    // Ajustar resolução do canvas
    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        canvas.width  = rect.width  * window.devicePixelRatio;
        canvas.height = rect.height * window.devicePixelRatio;
        ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
    }
    resizeCanvas();

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const src = e.touches ? e.touches[0] : e;
        return { x: src.clientX - rect.left, y: src.clientY - rect.top };
    }
    function start(e) { e.preventDefault(); drawing = true; const p = getPos(e); lastX = p.x; lastY = p.y; }
    function draw(e) {
        e.preventDefault(); if (!drawing) return;
        const p = getPos(e);
        ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(p.x, p.y);
        ctx.strokeStyle = '#1e293b'; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.stroke();
        lastX = p.x; lastY = p.y;
    }
    function end() { drawing = false; }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, {passive:false});
    canvas.addEventListener('touchmove', draw, {passive:false});
    canvas.addEventListener('touchend', end);
}

function clearSignature() {
    if (canvas) { const ctx = canvas.getContext('2d'); ctx.clearRect(0, 0, canvas.width, canvas.height); }
}

function saveSignature() {
    const name = document.getElementById('sig-name').value.trim();
    if (!name) { alert('Informe o nome do signatário.'); return; }
    if (!canvas) return;

    document.getElementById('sig-form-name').value = name;
    document.getElementById('sig-form-doc').value  = document.getElementById('sig-doc').value;
    document.getElementById('sig-form-data').value = canvas.toDataURL('image/png');

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            document.getElementById('sig-form-lat').value = pos.coords.latitude;
            document.getElementById('sig-form-lng').value = pos.coords.longitude;
            document.getElementById('sig-form').submit();
        }, () => document.getElementById('sig-form').submit(), {timeout:5000});
    } else {
        document.getElementById('sig-form').submit();
    }
}

// ── Preview upload ────────────────────────────────────────────────────────────
const uploadInput = document.getElementById('upload-input');
const uploadLabel = document.getElementById('upload-label');
const uploadPreview = document.getElementById('upload-preview');
if (uploadInput) {
    uploadInput.addEventListener('change', function () {
        if (this.files.length > 0) {
            uploadPreview.style.display = 'block';
            uploadPreview.textContent = this.files.length + ' arquivo(s) selecionado(s)';
            uploadLabel.style.borderColor = '#6366f1';
        }
    });
}
</script>
@endpush
