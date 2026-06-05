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
                <div class="form-group">
                    <label class="form-label" for="client_id">Cliente *</label>
                    <select id="client_id" name="client_id" class="form-control {{ $errors->has('client_id') ? 'is-invalid' : '' }}" required>
                        <option value="">Selecione o cliente...</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id', request('client_id')) == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="equipment_id">Equipamento do Cliente</label>
                    <select id="equipment_id" name="equipment_id" class="form-control {{ $errors->has('equipment_id') ? 'is-invalid' : '' }}">
                        <option value="">Selecione o cliente primeiro...</option>
                    </select>
                    @error('equipment_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="grid-2">
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
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="scheduled_at">Data/Hora Agendada</label>
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at') }}" class="form-control">
                </div>
                <div></div>
            </div>

            <div class="form-group mt-3">
                <label class="form-label" for="description">Descrição do Problema *</label>
                <textarea id="description" name="description" rows="4" class="form-control {{ $errors->has('description') ? 'is-invalid' : '' }}" placeholder="Descreva detalhadamente o problema ou serviço a ser executado..." required>{{ old('description') }}</textarea>
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
        const clientSelect = document.getElementById('client_id');
        const equipmentSelect = document.getElementById('equipment_id');

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

        // Handle client selection change
        clientSelect.addEventListener('change', function() {
            loadEquipments(this.value);
        });

        // Trigger on load if client is already selected
        if (clientSelect.value) {
            loadEquipments(clientSelect.value, "{{ old('equipment_id') }}");
        }
    });
</script>
@endpush
@endsection
