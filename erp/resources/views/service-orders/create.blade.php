@extends('layouts.app')
@section('title', 'Nova Ordem de Serviço')

@section('content')
<div style="max-width:720px">
    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('service-orders.index') }}" class="btn btn-secondary btn-sm">← Voltar</a>
    </div>

    <div class="card">
        <h2 class="font-bold mb-4 flex items-center gap-2" style="font-size:16px"><x-heroicon-o-wrench-screwdriver class="w-5 h-5 text-indigo-600"/> Nova Ordem de Serviço</h2>

        <form method="POST" action="{{ route('service-orders.store') }}">
            @csrf

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

                    <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id', request('client_id')) }}" required>
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
                            <option value="{{ $tech->id }}" {{ old('technician_id') == $tech->id ? 'selected' : '' }}>
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
                        @foreach($priorities as $p)
                            <option value="{{ $p['value'] }}" {{ old('priority', 'normal') == $p['value'] ? 'selected' : '' }}>
                                {{ $p['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="scheduled_at">Data/Hora Agendada</label>
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at') }}" class="form-control">
                </div>
            </div>

            <div class="form-group mt-3">
                <label class="form-label" for="description">Descrição do Problema *</label>
                <textarea id="description" name="description" rows="4" class="form-control {{ $errors->has('description') ? 'is-invalid' : '' }}" placeholder="Descreva detalhadamente o problem ou serviço a ser executado..." required>{{ old('description') }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="internal_notes">Observações Internas</label>
                <textarea id="internal_notes" name="internal_notes" rows="2" class="form-control" placeholder="Observações visíveis apenas para a equipe...">{{ old('internal_notes') }}</textarea>
            </div>

            <div class="flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary btn-lg flex items-center gap-2"><x-heroicon-o-check class="w-5 h-5"/> Criar Ordem de Serviço</button>
                <a href="{{ route('service-orders.index') }}" class="btn btn-secondary btn-lg">Cancelar</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
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

        function selectClient(client) {
            hiddenClientId.value = client.id;
            clientCardName.textContent = client.name;
            clientCardDocument.textContent = `CPF/CNPJ: ${client.document}`;
            clientSearchContainer.style.display = 'none';
            clientDetailsCard.style.display = 'block';
            
            clientInput.value = '';
            clientResults.style.display = 'none';
            
            loadAddresses(client.id, "{{ old('client_address_id') }}");
            loadEquipments(client.id, "{{ old('equipment_id') }}");
        }

        window.clearSelectedClient = function() {
            hiddenClientId.value = '';
            clientSearchContainer.style.display = 'block';
            clientDetailsCard.style.display = 'none';
            clientInput.focus();
            
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
            if (e.target !== clientInput) {
                clientResults.style.display = 'none';
            }
        });

        // Inicializar com cliente selecionado se houver no form (old ou request)
        @php
            $selectedClient = null;
            $selectedClientId = old('client_id', request('client_id'));
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
</script>

<style>
    .hover-results:hover {
        background-color: #f8fafc !important;
    }
</style>
@endpush
@endsection
