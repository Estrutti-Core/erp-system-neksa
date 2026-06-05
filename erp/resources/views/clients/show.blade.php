@extends('layouts.app')
@section('title', $client->name)

@section('topbar-actions')
    @can('update', $client)
        <a href="{{ route('clients.edit', $client) }}" class="btn btn-primary btn-sm flex items-center gap-1"><x-heroicon-o-pencil-square class="w-4 h-4"/> Editar</a>
    @endcan
    <a href="{{ route('service-orders.create') }}?client_id={{ $client->id }}" class="btn btn-secondary btn-sm">+ Nova OS</a>
@endsection

@section('content')
<div class="flex items-center gap-3 mb-4">
    <a href="{{ route('clients.index') }}" class="btn btn-secondary btn-sm">← Voltar</a>
    @if(!$client->is_active)<span class="badge badge-slate">Inativo</span>@endif
</div>

<div class="grid-2">
    <div>
        <!-- Card 1: Identificação Básica -->
        <div class="card mb-4 shadow-sm" style="border-radius:12px; border:1px solid #e2e8f0; padding:24px;">
            <h2 class="font-bold mb-4 flex items-center gap-2" style="font-size:16px"><x-heroicon-o-user class="w-5 h-5 text-indigo-600"/> {{ $client->name }}</h2>
            
            <div class="grid-2">
                <div>
                    <div class="text-xs text-muted">Documento</div>
                    <div class="font-semibold mt-1">{{ $client->formatted_document ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Tipo</div>
                    <div class="font-semibold mt-1">{{ strtoupper($client->document_type ?? '—') }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Telefone</div>
                    <div class="font-semibold mt-1">{{ $client->phone ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Telefone 2</div>
                    <div class="font-semibold mt-1">{{ $client->phone_secondary ?? '—' }}</div>
                </div>
                <div style="grid-column:1/-1">
                    <div class="text-xs text-muted">E-mail</div>
                    <div class="font-semibold mt-1">{{ $client->email ?? '—' }}</div>
                </div>
            </div>

            @if($client->notes)
            <div class="border-t mt-3 pt-3">
                <div class="text-xs text-muted mb-1">Observações Internas</div>
                <p class="text-sm" style="color: #475569;">{{ $client->notes }}</p>
            </div>
            @endif
        </div>

        <!-- Card 2: Dados Corporativos Receita Federal (Apenas se CNPJ) -->
        @if($client->document_type === 'cnpj')
        <div class="card mb-4 shadow-sm" style="border-radius:12px; border:1px solid #e2e8f0; padding:24px;">
            <h3 class="font-bold mb-4 flex items-center gap-2" style="font-size:15px; color:#1e293b;">
                <x-heroicon-o-building-office-2 class="w-5 h-5 text-indigo-600"/> Dados PJ (Receita Federal)
            </h3>
            
            <div class="grid-2 mb-3">
                <div>
                    <div class="text-xs text-muted">Razão Social</div>
                    <div class="font-semibold mt-1">{{ $client->social_name ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Nome Fantasia</div>
                    <div class="font-semibold mt-1">{{ $client->trade_name ?: '—' }}</div>
                </div>
            </div>

            <div class="grid-3 mb-3">
                <div>
                    <div class="text-xs text-muted">Setor</div>
                    <div class="font-semibold mt-1">{{ $client->sector ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Abertura</div>
                    <div class="font-semibold mt-1">{{ $client->opening_date ? $client->opening_date->format('d/m/Y') : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Capital Social</div>
                    <div class="font-semibold mt-1">R$ {{ $client->capital_social ? number_format($client->capital_social, 2, ',', '.') : '—' }}</div>
                </div>
            </div>

            <div class="grid-3 mb-4">
                <div>
                    <div class="text-xs text-muted">Porte</div>
                    <div class="font-semibold mt-1">{{ $client->company_size ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Natureza Jurídica</div>
                    <div class="font-semibold mt-1">{{ $client->legal_nature ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Situação Cadastral</div>
                    <div class="font-semibold mt-1">
                        <span class="badge badge-{{ strtolower($client->registration_status) === 'ativa' ? 'green' : 'slate' }}">
                            {{ $client->registration_status ?: '—' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- CNAEs -->
            @php $primaryCnae = $client->cnaes->where('pivot.is_primary', true)->first(); @endphp
            @if($primaryCnae)
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 12px;">
                <div class="text-xs text-muted font-bold">CNAE Principal</div>
                <div class="font-semibold mt-1 text-sm">
                    <span style="font-family: monospace; background:#e2e8f0; padding:2px 6px; border-radius:4px;">{{ $primaryCnae->code }}</span>
                    {{ $primaryCnae->description }}
                </div>
            </div>
            @endif

            @php $secCnaes = $client->cnaes->where('pivot.is_primary', false); @endphp
            @if($secCnaes->isNotEmpty())
            <div>
                <div class="text-xs text-muted mb-2 font-bold">CNAEs Secundários</div>
                <div class="flex flex-col gap-2">
                    @foreach($secCnaes as $cnae)
                    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 10px; font-size:12px;">
                        <span style="font-family: monospace; background:#f1f5f9; padding:2px 4px; border-radius:4px; font-weight:700;">{{ $cnae->code }}</span>
                        {{ $cnae->description }}
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif

        <!-- Card 3: Lista de Contatos -->
        <div class="card mb-4 shadow-sm" style="border-radius:12px; border:1px solid #e2e8f0; padding:24px;">
            <h3 class="font-bold mb-4 flex items-center gap-2" style="font-size:15px; color:#1e293b;">
                <x-heroicon-o-users class="w-5 h-5 text-indigo-600"/> Contatos Registrados
            </h3>
            
            <div class="flex flex-col gap-3">
                @forelse($client->contacts as $contact)
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
                    <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold" style="font-size:14px; color:#0f172a;">{{ $contact->name }}</span>
                            @if($contact->role)<span class="badge badge-slate" style="font-size: 10px;">{{ $contact->role }}</span>@endif
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if($contact->is_primary)
                                <span class="badge badge-blue" style="font-size: 10px;">Principal</span>
                            @endif
                        </div>
                    </div>

                    <div style="font-size: 13px; color: #475569; display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div>E-mail: {{ $contact->email ?: '—' }}</div>
                        <div>Telefone: {{ $contact->phone ?: '—' }}</div>
                        <div style="grid-column:1/-1">WhatsApp: {{ $contact->whatsapp ?: '—' }}</div>
                    </div>

                    <!-- Privacy Flags -->
                    <div style="margin-top: 10px; border-top: 1px dashed #e2e8f0; padding-top: 10px;" class="flex gap-3 flex-wrap">
                        <span class="flex items-center gap-1 text-xs font-semibold {{ $contact->is_phone_blocked ? 'text-red-600' : 'text-green-600' }}">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: {{ $contact->is_phone_blocked ? '#ef4444' : '#10b981' }}"></span>
                            Ligações: {{ $contact->is_phone_blocked ? 'Bloqueado' : 'Liberado' }}
                        </span>
                        <span class="flex items-center gap-1 text-xs font-semibold {{ $contact->is_whatsapp_blocked ? 'text-red-600' : 'text-green-600' }}">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: {{ $contact->is_whatsapp_blocked ? '#ef4444' : '#10b981' }}"></span>
                            WhatsApp: {{ $contact->is_whatsapp_blocked ? 'Bloqueado' : 'Liberado' }}
                        </span>
                        <span class="flex items-center gap-1 text-xs font-semibold {{ $contact->is_email_blocked ? 'text-red-600' : 'text-green-600' }}">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: {{ $contact->is_email_blocked ? '#ef4444' : '#10b981' }}"></span>
                            E-mail: {{ $contact->is_email_blocked ? 'Bloqueado' : 'Liberado' }}
                        </span>
                    </div>
                </div>
                @empty
                <p style="text-align: center; color: #94a3b8; font-size: 13px;">Nenhum contato cadastrado.</p>
                @endforelse
            </div>
        </div>

        <!-- Card 3.5: Equipamentos -->
        <div class="card mb-4 shadow-sm" style="border-radius:12px; border:1px solid #e2e8f0; padding:24px;">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold flex items-center gap-2" style="font-size:15px; color:#1e293b;">
                    <x-heroicon-o-computer-desktop class="w-5 h-5 text-indigo-600"/> Equipamentos do Cliente
                </h3>
                <button type="button" onclick="openAddEquipmentModal()" class="btn btn-primary btn-sm flex items-center gap-1">
                    + Novo
                </button>
            </div>
            
            <div class="flex flex-col gap-3">
                @forelse($client->equipments as $equip)
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;" class="flex justify-between items-start gap-4">
                    <div style="flex: 1; min-width: 0;">
                        <div class="font-semibold" style="font-size:14px; color:#0f172a;">{{ $equip->name }}</div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 4px;" class="flex flex-wrap gap-x-4 gap-y-1">
                            @if($equip->brand)<div><strong>Marca:</strong> {{ $equip->brand }}</div>@endif
                            @if($equip->model)<div><strong>Modelo:</strong> {{ $equip->model }}</div>@endif
                            @if($equip->serial_number)<div><strong>S/N:</strong> {{ $equip->serial_number }}</div>@endif
                        </div>
                        @if($equip->notes)
                        <div style="font-size:12px; color:#475569; margin-top: 6px; border-top: 1px dashed #e2e8f0; padding-top: 6px;">
                            {{ $equip->notes }}
                        </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="openEditEquipmentModal({{ json_encode($equip) }})" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size:11px;">
                            Editar
                        </button>
                        <form action="{{ route('equipments.destroy', $equip) }}" method="POST" onsubmit="return confirm('Deseja realmente remover este equipamento?')" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-secondary btn-sm text-red-600 hover:bg-red-50" style="padding: 4px 8px; font-size:11px; border-color:#fee2e2;">
                                Excluir
                            </button>
                        </form>
                    </div>
                </div>
                @empty
                <p style="text-align: center; color: #94a3b8; font-size: 13px;">Nenhum equipamento cadastrado.</p>
                @endforelse
            </div>
        </div>

        <!-- Card 4: Endereço -->
        @foreach($client->addresses as $addr)
        <div class="card mb-4 shadow-sm" style="border-radius:12px; border:1px solid #e2e8f0; padding:24px;">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-semibold flex items-center gap-1" style="font-size:14px"><x-heroicon-o-map-pin class="w-4 h-4 text-indigo-600"/> {{ $addr->label }}</h3>
                @if($addr->is_primary)<span class="badge badge-blue" style="font-size:11px">Principal</span>@endif
            </div>
            <p class="text-sm" style="color: #475569;">{{ $addr->full_address }}</p>
            @if($addr->hasCoordinates())
            <div class="text-xs text-muted mt-2">Latitude: {{ $addr->latitude }} · Longitude: {{ $addr->longitude }}</div>
            <div id="minimap-{{ $addr->id }}" style="height:150px;border-radius:8px;margin-top:8px;border:1px solid #e2e8f0"></div>
            @push('scripts')
            <script>
            (function(){
                const m = L.map('minimap-{{ $addr->id }}', {zoomControl:false, dragging:false, scrollWheelZoom:false})
                    .setView([{{ $addr->latitude }}, {{ $addr->longitude }}], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(m);
                L.marker([{{ $addr->latitude }}, {{ $addr->longitude }}]).addTo(m);
            })();
            </script>
            @endpush
            @endif
        </div>
        @endforeach
    </div>

    <!-- Histórico de OS -->
    <div>
        <div class="card shadow-sm" style="border-radius:12px; border:1px solid #e2e8f0; padding:24px;">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold flex items-center gap-2" style="font-size:15px"><x-heroicon-o-wrench-screwdriver class="w-5 h-5 text-indigo-600"/> Histórico de OS ({{ $client->serviceOrders->count() }})</h3>
                <a href="{{ route('service-orders.create') }}?client_id={{ $client->id }}" class="btn btn-primary btn-sm">+ Nova</a>
            </div>
            @forelse($client->serviceOrders as $os)
            <a href="{{ route('service-orders.show', $os) }}" style="display:flex;gap:12px;align-items:flex-start;text-decoration:none;color:inherit;padding:12px 0;border-bottom:1px solid #f1f5f9">
                <div style="width:40px;height:40px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#4f46e5;flex-shrink:0"><x-heroicon-o-wrench-screwdriver class="w-5 h-5"/></div>
                <div style="flex:1;min-width:0">
                    <div class="flex justify-between items-center">
                        <span style="font-size:12px;font-weight:700;color:#64748b">{{ $os->code }}</span>
                        <span class="badge badge-{{ $os->status->color() }}">{{ $os->status->label() }}</span>
                    </div>
                    <div style="font-size:13px;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $os->description }}</div>
                    <div class="text-xs text-muted mt-1">
                        {{ $os->created_at->format('d/m/Y') }}
                        @if($os->technician) · {{ $os->technician->name }} @endif
                        · <strong>R$ {{ number_format($os->total_amount, 2, ',', '.') }}</strong>
                    </div>
                </div>
            </a>
            @empty
            <div style="text-align:center;padding:32px;color:#94a3b8">
                <div class="flex justify-center" style="margin-bottom:8px"><x-heroicon-o-clipboard-document-list class="w-10 h-10 text-gray-300"/></div>
                <div>Nenhuma OS encontrada</div>
            </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Modal Equipamento -->
<div id="equipment-modal" class="fixed inset-0 bg-slate-900/50 hidden items-center justify-center z-50 p-4" style="backdrop-filter: blur(4px);">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200" style="border: 1px solid #e2e8f0;">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
            <h4 id="equipment-modal-title" class="font-bold text-slate-800" style="font-size: 15px;">Novo Equipamento</h4>
            <button type="button" onclick="closeEquipmentModal()" class="text-slate-400 hover:text-slate-600 font-bold" style="font-size:16px;">✕</button>
        </div>
        <form id="equipment-modal-form" method="POST">
            @csrf
            <div id="equipment-modal-method-container"></div>
            <div class="px-6 py-4 flex flex-col gap-4">
                <div class="form-group">
                    <label class="form-label" for="equip-name">Equipamento / Descrição <span style="color:#ef4444">*</span></label>
                    <input type="text" id="equip-name" name="name" class="form-control" placeholder="Ex: Ar Condicionado 12k BTU" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="equip-brand">Marca</label>
                        <input type="text" id="equip-brand" name="brand" class="form-control" placeholder="Ex: Samsung">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="equip-model">Modelo</label>
                        <input type="text" id="equip-model" name="model" class="form-control" placeholder="Ex: WindFree">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="equip-serial">Número de Série</label>
                    <input type="text" id="equip-serial" name="serial_number" class="form-control" placeholder="Ex: 123456789">
                </div>
                <div class="form-group">
                    <label class="form-label" for="equip-notes">Observações</label>
                    <textarea id="equip-notes" name="notes" class="form-control" rows="3" placeholder="Histórico, voltagem, especificações adicionais..."></textarea>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t flex justify-end gap-2">
                <button type="button" onclick="closeEquipmentModal()" class="btn btn-secondary btn-sm">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openAddEquipmentModal() {
    const modal = document.getElementById('equipment-modal');
    document.getElementById('equipment-modal-title').innerText = 'Novo Equipamento';
    document.getElementById('equipment-modal-form').action = "{{ route('clients.equipments.store', $client) }}";
    document.getElementById('equipment-modal-method-container').innerHTML = '';
    
    document.getElementById('equip-name').value = '';
    document.getElementById('equip-brand').value = '';
    document.getElementById('equip-model').value = '';
    document.getElementById('equip-serial').value = '';
    document.getElementById('equip-notes').value = '';
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function openEditEquipmentModal(equip) {
    const modal = document.getElementById('equipment-modal');
    document.getElementById('equipment-modal-title').innerText = 'Editar Equipamento';
    document.getElementById('equipment-modal-form').action = `/equipments/${equip.id}`;
    document.getElementById('equipment-modal-method-container').innerHTML = '@method("PUT")';
    
    document.getElementById('equip-name').value = equip.name || '';
    document.getElementById('equip-brand').value = equip.brand || '';
    document.getElementById('equip-model').value = equip.model || '';
    document.getElementById('equip-serial').value = equip.serial_number || '';
    document.getElementById('equip-notes').value = equip.notes || '';
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeEquipmentModal() {
    const modal = document.getElementById('equipment-modal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}
</script>
@endpush
@endsection
