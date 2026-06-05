@extends('layouts.app')
@section('title', 'Editar Status: ' . $status->name)

@section('content')
<div class="flex items-center gap-3 mb-4">
    <a href="{{ route('settings.statuses.index') }}" class="btn btn-secondary btn-sm">← Voltar</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form method="POST" action="{{ route('settings.statuses.update', $status) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="name" class="form-label">Nome do Status</label>
            <input type="text" name="name" id="name" value="{{ old('name', $status->name) }}" class="form-control @error('name') is-invalid @enderror" required>
            @error('name')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="color" class="form-label">Cor / Badge CSS Class</label>
            <select name="color" id="color" class="form-control @error('color') is-invalid @enderror" required>
                <option value="blue" {{ old('color', $status->color) == 'blue' ? 'selected' : '' }}>Azul (Padrão/Aberto)</option>
                <option value="green" {{ old('color', $status->color) == 'green' ? 'selected' : '' }}>Verde (Concluído/Sucesso)</option>
                <option value="red" {{ old('color', $status->color) == 'red' ? 'selected' : '' }}>Vermelho (Cancelado/Erro)</option>
                <option value="amber" {{ old('color', $status->color) == 'amber' ? 'selected' : '' }}>Âmbar (Atenção/Pendente)</option>
                <option value="slate" {{ old('color', $status->color) == 'slate' ? 'selected' : '' }}>Cinza/Slate (Neutro)</option>
                <option value="indigo" {{ old('color', $status->color) == 'indigo' ? 'selected' : '' }}>Índigo (Destaque)</option>
                <option value="purple" {{ old('color', $status->color) == 'purple' ? 'selected' : '' }}>Roxo</option>
                <option value="pink" {{ old('color', $status->color) == 'pink' ? 'selected' : '' }}>Rosa</option>
                <option value="emerald" {{ old('color', $status->color) == 'emerald' ? 'selected' : '' }}>Esmeralda</option>
                <option value="cyan" {{ old('color', $status->color) == 'cyan' ? 'selected' : '' }}>Ciano</option>
            </select>
            @error('color')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label for="expected_time_minutes" class="form-label">Tempo Esperado (minutos)</label>
                <input type="number" name="expected_time_minutes" id="expected_time_minutes" value="{{ old('expected_time_minutes', $status->expected_time_minutes) }}" min="1" class="form-control @error('expected_time_minutes') is-invalid @enderror" placeholder="Opcional">
                @error('expected_time_minutes')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="max_stay_minutes" class="form-label">Limite SLA Máximo (minutos)</label>
                <input type="number" name="max_stay_minutes" id="max_stay_minutes" value="{{ old('max_stay_minutes', $status->max_stay_minutes) }}" min="1" class="form-control @error('max_stay_minutes') is-invalid @enderror" placeholder="Opcional">
                @error('max_stay_minutes')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-group" style="margin-top: 10px;">
            <label class="form-label">Propriedades do Estado</label>
            
            <div style="margin-top: 8px;">
                <label class="flex items-center gap-2" style="font-weight: normal; cursor: pointer;">
                    <input type="checkbox" name="is_open_state" value="1" {{ old('is_open_state', $status->is_open_state) ? 'checked' : '' }}>
                    <span>Representa um estado aberto/ativo</span>
                </label>
                @error('is_open_state')
                    <span class="invalid-feedback block">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-top: 8px;">
                <label class="flex items-center gap-2" style="font-weight: normal; cursor: pointer;">
                    <input type="checkbox" name="is_completed_state" value="1" {{ old('is_completed_state', $status->is_completed_state) ? 'checked' : '' }}>
                    <span>Representa o estado de conclusão (Finalizado)</span>
                </label>
                @error('is_completed_state')
                    <span class="invalid-feedback block">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-top: 8px;">
                <label class="flex items-center gap-2" style="font-weight: normal; cursor: pointer;">
                    <input type="checkbox" name="is_cancelled_state" value="1" {{ old('is_cancelled_state', $status->is_cancelled_state) ? 'checked' : '' }}>
                    <span>Representa o estado de cancelamento (Cancelado)</span>
                </label>
                @error('is_cancelled_state')
                    <span class="invalid-feedback block">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-group" style="border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 20px;">
            <label class="form-label">Transições Permitidas (A partir deste status)</label>
            <p class="text-xs text-muted" style="margin-top:-4px; margin-bottom: 8px;">Selecione para quais outros status o usuário pode mover a OS diretamente.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px;">
                @foreach($statuses as $other)
                    <label class="flex items-center gap-2" style="font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="allowed_transitions[]" value="{{ $other->id }}" 
                            {{ is_array(old('allowed_transitions', $allowedTransitionIds)) && in_array($other->id, old('allowed_transitions', $allowedTransitionIds)) ? 'checked' : '' }}>
                        <span class="badge badge-{{ $other->color }} text-xs">{{ $other->name }}</span>
                    </label>
                @endforeach
            </div>
            @error('allowed_transitions')
                <span class="invalid-feedback block mt-1">{{ $message }}</span>
            @enderror
        </div>

        <div style="margin-top: 24px; text-align: right;">
            <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">Salvar Alterações</button>
        </div>
    </form>
</div>
@endsection
