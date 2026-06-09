@extends('layouts.app')
@section('title', 'Nova Conta Financeira')

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="{{ route('financial-accounts.store') }}" method="POST">
        @csrf

        <div class="form-group mb-4">
            <label for="name" class="form-label">Nome da Conta *</label>
            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Ex: Banco do Brasil PJ, Nubank Caixa" required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mb-4">
            <label for="type_id" class="form-label">Tipo de Conta *</label>
            <select name="type_id" id="type_id" class="form-control @error('type_id') is-invalid @enderror" required>
                <option value="">Selecione um tipo...</option>
                @foreach($types as $type)
                    <option value="{{ $type->id }}" {{ old('type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                @endforeach
            </select>
            @error('type_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mb-4">
            <label for="bank_name" class="form-label">Nome do Banco (Opcional)</label>
            <input type="text" name="bank_name" id="bank_name" class="form-control @error('bank_name') is-invalid @enderror" value="{{ old('bank_name') }}" placeholder="Ex: Banco do Brasil, Nubank, Itaú">
            @error('bank_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="flex gap-4">
            <div class="form-group mb-4 flex-1">
                <label for="agency" class="form-label">Agência (Opcional)</label>
                <input type="text" name="agency" id="agency" class="form-control @error('agency') is-invalid @enderror" value="{{ old('agency') }}" placeholder="Ex: 0001, 1234">
                @error('agency')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-4 flex-1">
                <label for="account_number" class="form-label">Número da Conta (Opcional)</label>
                <input type="text" name="account_number" id="account_number" class="form-control @error('account_number') is-invalid @enderror" value="{{ old('account_number') }}" placeholder="Ex: 12345-6">
                @error('account_number')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-group mb-4">
            <label for="balance" class="form-label">Saldo Inicial (R$) *</label>
            <input type="number" step="0.01" name="balance" id="balance" class="form-control @error('balance') is-invalid @enderror" value="{{ old('balance', '0.00') }}" required>
            @error('balance')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mb-4 flex items-center gap-2">
            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
            <label for="is_active" class="form-label" style="margin-bottom:0">Conta Ativa</label>
        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="{{ route('financial-accounts.index') }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection
