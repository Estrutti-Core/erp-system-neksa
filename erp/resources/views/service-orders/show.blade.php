@extends('layouts.app')
@section('title', 'OS ' . $serviceOrder->code)

@section('topbar-actions')
    <form action="{{ route('service-orders.duplicate', $serviceOrder) }}" method="POST" style="display:inline;" onsubmit="return confirm('Duplicar esta OS?')">
        @csrf
        <button type="submit" class="btn btn-secondary btn-sm">Duplicar</button>
    </form>
    <a href="{{ route('service-orders.pdf', $serviceOrder) }}" class="btn btn-secondary btn-sm" target="_blank">PDF Técnico</a>
    <a href="{{ route('service-orders.pdf', [$serviceOrder, 'mode' => 'receipt']) }}" class="btn btn-secondary btn-sm" target="_blank">Recibo do Cliente</a>
    <a href="{{ route('service-orders.fiscal', $serviceOrder) }}" class="btn btn-secondary btn-sm">Fiscal</a>
    @can('update', $serviceOrder)
        <button type="button" id="btn-top-edit" onclick="toggleEditMode(true)" class="btn btn-primary btn-sm" style="display: {{ ($errors->any() || request('edit')) ? 'none' : 'inline-block' }};">Editar</button>
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
{{-- COLUNA PRINCIPAL (ESQUERDA) --}}
<div>

{{-- Informações da OS --}}
<div class="card mb-4" id="os-details-card">
    <div id="os-details-view" style="display: {{ ($errors->any() || request('edit')) ? 'none' : 'block' }};">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h2 class="font-bold flex items-center gap-2" style="font-size:16px; margin: 0;">
                <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-indigo-600"/> {{ $serviceOrder->code }}
            </h2>
            @can('update', $serviceOrder)
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEditMode(true)" style="padding: 4px 8px; font-size: 12px;">Editar Dados</button>
            @endcan
        </div>
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
        @if($serviceOrder->internal_notes)
        <div class="mt-3 border-t pt-3">
            <div class="text-xs text-muted mb-1">Observações Internas</div>
            <p style="font-size:14px;line-height:1.6;margin:0">{{ $serviceOrder->internal_notes }}</p>
        </div>
        @endif
    </div>

    @can('update', $serviceOrder)
    <div id="os-details-edit" style="display: {{ ($errors->any() || request('edit')) ? 'block' : 'none' }};">
        <h2 class="font-bold mb-4 flex items-center gap-2" style="font-size:16px">
            <x-heroicon-o-wrench-screwdriver class="w-5 h-5 text-indigo-600"/> Editar Dados da OS
        </h2>

        <form method="POST" action="{{ route('service-orders.update', $serviceOrder) }}">
            @csrf
            @method('PUT')

            <div class="grid-2">
                <div class="form-group" style="position: relative;">
                    <label class="form-label">Cliente *</label>
                    
                    <!-- Container de Busca (Escondido após seleção) -->
                    <div id="client-search-container" style="position: relative;">
                        <input type="text" id="client-search-input" class="form-control {{ $errors->has('client_id') ? 'is-invalid' : '' }}" placeholder="Buscar cliente por nome ou CNPJ..." autocomplete="off">
                        <div id="client-autocomplete-results" style="display: none; position: absolute; left: 0; right: 0; top: 46px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; z-index: 100; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); max-height: 240px; overflow-y: auto;">
                        </div>
                    </div>

                    <!-- Card do Cliente Selecionado -->
                    <div id="client-details-card" style="display: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; position: relative;">
                        <div style="font-weight: 700; color: #0f172a; font-size: 14px;" id="client-card-name"></div>
                        <div style="color: #64748b; font-size: 12px; margin-top: 4px;" id="client-card-document"></div>
                        <button type="button" onclick="clearSelectedClient()" style="position: absolute; right: 12px; top: 12px; color: #ef4444; border: none; background: transparent; cursor: pointer; font-size: 12px; font-weight: 600;">Alterar</button>
                    </div>

                    <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id', $serviceOrder->client_id) }}" required>
                    @error('client_id')<div class="invalid-feedback" style="display:block;">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="client_address_id">Endereço de Atendimento <span style="color:#ef4444">*</span></label>
                    <select id="client_address_id" name="client_address_id" class="form-control {{ $errors->has('client_address_id') ? 'is-invalid' : '' }}" required disabled>
                        <option value="">Selecione o cliente primeiro...</option>
                    </select>
                    @error('client_address_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="equipment_id">Equipamento do Cliente</label>
                    <select id="equipment_id" name="equipment_id" class="form-control {{ $errors->has('equipment_id') ? 'is-invalid' : '' }}">
                        <option value="">Selecione o cliente primeiro...</option>
                    </select>
                    @error('equipment_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="technician_id">Técnico Responsável</label>
                    <select id="technician_id" name="technician_id" class="form-control">
                        <option value="">A definir...</option>
                        @foreach($technicians as $tech)
                            <option value="{{ $tech->id }}" {{ old('technician_id', $serviceOrder->technician_id) == $tech->id ? 'selected' : '' }}>
                                {{ $tech->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="priority">Prioridade *</label>
                    <select id="priority" name="priority" class="form-control {{ $errors->has('priority') ? 'is-invalid' : '' }}" required>
                        @foreach(\App\Enums\ServiceOrderPriority::cases() as $p)
                            <option value="{{ $p->value }}" {{ old('priority', $serviceOrder->priority->value) == $p->value ? 'selected' : '' }}>
                                {{ $p->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="scheduled_at">Data/Hora Agendada</label>
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at', $serviceOrder->scheduled_at?->format('Y-m-d\TH:i')) }}" class="form-control">
                </div>
            </div>

            <div class="form-group mt-3">
                <label class="form-label" for="description">Descrição do Problema *</label>
                <textarea id="description" name="description" rows="4" class="form-control {{ $errors->has('description') ? 'is-invalid' : '' }}" required>{{ old('description', $serviceOrder->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="services_performed">Serviços Executados</label>
                <textarea id="services_performed" name="services_performed" rows="3" class="form-control" placeholder="Descreva os serviços realizados pelo técnico...">{{ old('services_performed', $serviceOrder->services_performed) }}</textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="service_amount">Valor de Mão de Obra (R$)</label>
                    <input type="number" step="0.01" id="service_amount" name="service_amount" value="{{ old('service_amount', $serviceOrder->service_amount) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="parts_amount">Valor de Peças/Materiais (R$)</label>
                    <input type="number" step="0.01" id="parts_amount" name="parts_amount" value="{{ old('parts_amount', $serviceOrder->parts_amount) }}" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="internal_notes">Observações Internas</label>
                <textarea id="internal_notes" name="internal_notes" rows="2" class="form-control" placeholder="Observações visíveis apenas para a equipe...">{{ old('internal_notes', $serviceOrder->internal_notes) }}</textarea>
            </div>

            <div class="flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary btn-lg flex items-center gap-2"><x-heroicon-o-check class="w-5 h-5"/> Salvar Alterações</button>
                <button type="button" class="btn btn-secondary btn-lg" onclick="toggleEditMode(false)">Cancelar</button>
            </div>
        </form>
    </div>
    @endcan
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

{{-- Checklists Operacionais (Respostas inline integradas!) --}}
@if($serviceOrder->checklists->isNotEmpty())
<div class="card mb-4">
    <h3 class="font-bold mb-3 flex items-center gap-2">
        <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-indigo-600"/> Checklists Operacionais
    </h3>
    <div style="display:flex;flex-direction:column;gap:12px;">
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
                @if($checklist->isFilled())
                    <a href="{{ route('service-orders.checklists.pdf', [$serviceOrder, $checklist]) }}"
                       target="_blank"
                       class="btn btn-secondary btn-sm"
                       style="white-space:nowrap;gap:4px;display:inline-flex;align-items:center;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:13px;height:13px"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                        PDF
                    </a>
                @endif
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

            {{-- Exibição de Respostas Inline para Auditoria Operacional Unificada --}}
            @if($checklist->isFilled() && !$checklist->is_inactive)
            <div style="margin-top:12px;border-top:1px solid #f1f5f9;padding-top:10px;">
                <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px">Respostas do Checklist:</div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    @foreach($checklist->instancedQuestions as $q)
                        @if($q->question_type === 'label') @continue @endif
                        <div style="font-size:12px;line-height:1.4;padding:4px 0;border-bottom:1px solid #f8fafc;">
                            <span style="color:#475569;font-weight:600;">{{ $q->question_text }}:</span>
                            <span style="color:#1e293b;font-weight:500;">
                                @php $av = $q->answer?->answer_value; @endphp
                                @if($q->question_type === 'checkbox')
                                    {{ $av === 'sim' ? '✓ Sim' : ($av === 'não' ? '✗ Não' : '—') }}
                                @elseif($q->question_type === 'photo')
                                    @php $photoCount = count($q->answer?->photos_urls ?? []); @endphp
                                    {{ $photoCount > 0 ? $photoCount . ' foto(s)' : '—' }}
                                @elseif($q->question_type === 'signature')
                                    {{ $av && str_starts_with($av, 'data:') ? '✓ Assinado' : '—' }}
                                @else
                                    {{ $av ?? '—' }}
                                @endif
                            </span>
                            @if($q->answer?->observation)
                                <span style="color:#94a3b8;font-size:11px;font-style:italic;"> — {{ Str::limit($q->answer->observation, 80) }}</span>
                            @endif
                            @php $urls = $q->answer?->photos_urls ?? []; @endphp
                            @if(count($urls) > 0)
                                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;">
                                    @foreach($urls as $u)
                                        <a href="{{ $u }}" target="_blank">
                                            <img src="{{ $u }}" style="width:44px;height:44px;object-fit:cover;border-radius:4px;border:1px solid #e2e8f0;" alt="Foto">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Itens / Peças --}}
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

{{-- COLUNA LATERAL (DIREITA) --}}
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

{{-- Financeiro Integrado da Ordem de Serviço (Baixas Inline) --}}
<div class="card mb-4">
    <h3 class="font-bold mb-3 flex items-center gap-2"><x-heroicon-o-currency-dollar class="w-5 h-5 text-indigo-600"/> Financeiro e Parcelamento</h3>
    
    @if($serviceOrder->payments->isEmpty())
        <p class="text-sm text-muted">A OS ainda não foi faturada (aguardando transição para status de fechamento).</p>
    @else
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
            @foreach($serviceOrder->payments as $payment)
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;font-size:12px;">
                    <div style="display:flex;justify-content:between;font-weight:600;color:#1e293b;">
                        <span>{{ $payment->payment_method }}</span>
                        <span style="margin-left:auto">R$ {{ number_format($payment->amount, 2, ',', '.') }}</span>
                    </div>
                    <div class="text-xs text-muted mt-1">
                        {{ $payment->installments_count }}x · 1ª parc: {{ $payment->first_due_date->format('d/m/Y') }}
                    </div>
                </div>
            @endforeach
        </div>

        <h4 style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">Parcelas do Contas a Receber</h4>
        <div style="display:flex;flex-direction:column;gap:8px;">
            @forelse($serviceOrder->installments as $inst)
                <div style="border:1px solid #e2e8f0;border-radius:8px;padding:10px;background:#fff;font-size:12px;display:flex;align-items:center;justify-content:between">
                    <div>
                        <div class="font-semibold text-slate-700">Parcela {{ $inst->installment_number }}</div>
                        <div class="text-xs text-muted">Venc: {{ $inst->due_date->format('d/m/Y') }}</div>
                        <div class="font-bold text-slate-900 mt-1">R$ {{ number_format($inst->amount, 2, ',', '.') }}</div>
                    </div>
                    <div style="text-align:right;margin-left:auto">
                        @if($inst->status === 'paid' || $inst->paid_at)
                            <span class="badge badge-success" style="font-size:10px;padding:3px 8px;">Pago</span>
                            <div style="font-size:10px;color:#64748b;margin-top:4px;">Em {{ $inst->paid_at->format('d/m/Y') }}</div>
                        @else
                            <span class="badge badge-warning" style="font-size:10px;padding:3px 8px;margin-bottom:4px;display:inline-block;">Pendente</span>
                            @can('update', $serviceOrder)
                                <div>
                                    <button type="button" onclick="openPaymentModal({{ $inst->id }}, {{ $inst->amount }}, {{ $serviceOrder->receivable->id }})" class="btn btn-primary btn-sm" style="font-size:10px;padding:3px 8px;border-radius:6px;width:100%;justify-content:center">
                                        Baixar
                                    </button>
                                </div>
                            @endcan
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-muted">Sem parcelas registradas.</p>
            @endforelse
        </div>
    @endif
</div>

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

<!-- MODAL INLINE DE BAIXA DE PARCELA -->
<div id="payment-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 16px;">
    <div style="background: #fff; border-radius: 12px; width: 100%; max-width: 400px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 16px;">Baixar Parcela</h3>
        
        <form id="payment-form" method="POST" action="">
            @csrf
            <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
            
            <div class="form-group mb-3">
                <label class="form-label" style="font-weight: 600;">Valor Recebido</label>
                <input type="number" step="0.01" name="paid_amount" id="modal-paid-amount" class="form-control" required>
            </div>

            <div class="form-group mb-3">
                <label class="form-label" style="font-weight: 600;">Data do Recebimento</label>
                <input type="date" name="paid_at" value="{{ date('Y-m-d') }}" class="form-control" required>
            </div>

            <div class="form-group mb-3">
                <label class="form-label" style="font-weight: 600;">Meio de Pagamento</label>
                <select name="payment_method" class="form-control" required>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group mb-4">
                <label class="form-label" style="font-weight: 600;">Conta Financeira Destino</label>
                <select name="financial_account_id" class="form-control" required>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }} (Saldo: R$ {{ number_format($acc->balance, 2, ',', '.') }})</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="button" onclick="closePaymentModal()" class="btn btn-secondary flex-1" style="justify-content: center; border-radius: 8px;">Cancelar</button>
                <button type="submit" class="btn btn-primary flex-1" style="justify-content: center; border-radius: 8px;">Confirmar Baixa</button>
            </div>
        </form>
    </div>
</div>
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

// ── Modal de Baixa de Parcela ──────────────────────────────────────────────────
const modal = document.getElementById('payment-modal');
const form = document.getElementById('payment-form');
const amountInput = document.getElementById('modal-paid-amount');

function openPaymentModal(installmentId, amount, receivableId) {
    amountInput.value = amount;
    form.action = `/receivables/${receivableId}/installments/${installmentId}/pay`;
    modal.style.display = 'flex';
}

function closePaymentModal() {
    modal.style.display = 'none';
}

// ── Alternância de Modo (Visualização / Edição) ──────────────────────────────
function toggleEditMode(show) {
    const viewContainer = document.getElementById('os-details-view');
    const editContainer = document.getElementById('os-details-edit');
    const topEditBtn = document.getElementById('btn-top-edit');
    
    if (show) {
        if (viewContainer) viewContainer.style.display = 'none';
        if (editContainer) editContainer.style.display = 'block';
        if (topEditBtn) topEditBtn.style.display = 'none';
    } else {
        if (viewContainer) viewContainer.style.display = 'block';
        if (editContainer) editContainer.style.display = 'none';
        if (topEditBtn) topEditBtn.style.display = 'inline-block';
        
        // Remove edit query param if it was loaded with ?edit=1
        const url = new URL(window.location.href);
        if (url.searchParams.has('edit')) {
            url.searchParams.delete('edit');
            window.history.replaceState({}, '', url.toString());
        }
    }
}

// ── Cliente, Endereço e Equipamento (Edição) ──────────────────────────────────
@can('update', $serviceOrder)
document.addEventListener('DOMContentLoaded', function() {
    const clientInput = document.getElementById('client-search-input');
    const clientResults = document.getElementById('client-autocomplete-results');
    const hiddenClientId = document.getElementById('client_id');
    const selectAddress = document.getElementById('client_address_id');
    const equipmentSelect = document.getElementById('equipment_id');
    const clientSearchContainer = document.getElementById('client-search-container');
    const clientDetailsCard = document.getElementById('client-details-card');
    const clientCardName = document.getElementById('client-card-name');
    const clientCardDocument = document.getElementById('client-card-document');

    if (clientInput) {
        clientInput.addEventListener('input', function() {
            const query = this.value;
            if (query.length < 2) {
                clientResults.style.display = 'none';
                return;
            }

            fetch(`/quotes/search-clients?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    clientResults.innerHTML = '';
                    if (data.length === 0) {
                        clientResults.innerHTML = '<div style="padding: 10px 14px; color: #94a3b8; font-size: 13px;">Nenhum cliente encontrado</div>';
                    } else {
                        data.forEach(client => {
                            const item = document.createElement('div');
                            item.style.padding = '10px 14px';
                            item.style.cursor = 'pointer';
                            item.style.fontSize = '13px';
                            item.style.borderBottom = '1px solid #f1f5f9';
                            item.className = 'hover-results';
                            item.innerHTML = `<div style="font-weight: 600; color: #1e293b;">${client.name}</div><div style="font-size: 11px; color: #64748b;">CPF/CNPJ: ${client.document}</div>`;
                            
                            item.addEventListener('click', () => selectClient(client));
                            clientResults.appendChild(item);
                        });
                    }
                    clientResults.style.display = 'block';
                });
        });
    }

    function selectClient(client) {
        hiddenClientId.value = client.id;
        clientCardName.textContent = client.name;
        clientCardDocument.textContent = `CPF/CNPJ: ${client.document}`;
        clientSearchContainer.style.display = 'none';
        clientDetailsCard.style.display = 'block';
        
        if (clientInput) clientInput.value = '';
        if (clientResults) clientResults.style.display = 'none';
        
        loadAddresses(client.id, "{{ old('client_address_id', $serviceOrder->client_address_id) }}");
        loadEquipments(client.id, "{{ old('equipment_id', $serviceOrder->equipment_id) }}");
    }

    window.clearSelectedClient = function() {
        hiddenClientId.value = '';
        clientSearchContainer.style.display = 'block';
        clientDetailsCard.style.display = 'none';
        if (clientInput) clientInput.focus();
        
        selectAddress.disabled = true;
        selectAddress.innerHTML = '<option value="">Selecione o cliente primeiro...</option>';
        equipmentSelect.innerHTML = '<option value="">Selecione o cliente primeiro...</option>';
    }

    function loadAddresses(clientId, selectedId = null) {
        if (!clientId) {
            selectAddress.innerHTML = '<option value="">Selecione o cliente primeiro...</option>';
            selectAddress.disabled = true;
            return;
        }

        selectAddress.disabled = false;
        selectAddress.innerHTML = '<option value="">Carregando endereços...</option>';

        fetch(`/quotes/client-addresses/${clientId}`)
            .then(r => r.json())
            .then(data => {
                selectAddress.innerHTML = '';
                if (data.length === 0) {
                    selectAddress.innerHTML = '<option value="">Cliente sem endereços cadastrados</option>';
                } else {
                    data.forEach(addr => {
                        const opt = document.createElement('option');
                        opt.value = addr.id;
                        opt.textContent = addr.label + ' (' + addr.street + ', ' + addr.number + ')';
                        if (selectedId && addr.id == selectedId) {
                            opt.selected = true;
                        } else if (!selectedId && addr.is_primary) {
                            opt.selected = true;
                        }
                        selectAddress.appendChild(opt);
                    });
                }
            })
            .catch(err => {
                console.error(err);
                selectAddress.innerHTML = '<option value="">Erro ao carregar endereços</option>';
            });
    }

    function loadEquipments(clientId, selectedId = null) {
        if (!clientId) {
            equipmentSelect.innerHTML = '<option value="">Selecione o cliente primeiro...</option>';
            return;
        }

        equipmentSelect.innerHTML = '<option value="">Carregando...</option>';

        fetch(`/clients/${clientId}/equipments/json`)
            .then(r => r.json())
            .then(data => {
                equipmentSelect.innerHTML = '<option value="">Selecione o equipamento...</option>';
                data.forEach(e => {
                    const opt = document.createElement('option');
                    opt.value = e.id;
                    let text = e.name;
                    const details = [e.brand, e.model].filter(Boolean).join(' ');
                    if (details) text += ` (${details})`;
                    if (e.serial_number) text += ` - S/N: ${e.serial_number}`;
                    
                    opt.textContent = text;
                    if (selectedId && e.id == selectedId) {
                        opt.selected = true;
                    }
                    equipmentSelect.appendChild(opt);
                });
            })
            .catch(err => {
                console.error(err);
                equipmentSelect.innerHTML = '<option value="">Erro ao carregar equipamentos</option>';
            });
    }

    // Fechar resultados ao clicar fora
    document.addEventListener('click', function(e) {
        if (clientInput && e.target !== clientInput && clientResults) {
            clientResults.style.display = 'none';
        }
    });

    // Inicializar com cliente selecionado se houver no form (old ou request)
    @php
        $selectedClient = null;
        $selectedClientId = old('client_id', $serviceOrder->client_id);
        if ($selectedClientId) {
            $selectedClient = $clients->firstWhere('id', $selectedClientId);
        }
    @endphp
    @if($selectedClient)
        selectClient({
            id: "{{ $selectedClient->id }}",
            name: "{!! addslashes($selectedClient->name) !!}",
            document: "{{ $selectedClient->formatted_document }}"
        });
    @endif
});
@endcan
</script>
@endpush
