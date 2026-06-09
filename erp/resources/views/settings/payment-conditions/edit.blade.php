@extends('layouts.app')
@section('title', 'Editar Condição de Pagamento')

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="{{ route('payment-conditions.update', $paymentCondition) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-group mb-4">
            <label for="name" class="form-label">Nome da Condição *</label>
            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $paymentCondition->name) }}" placeholder="Ex: Pix à Vista, Cartão 3x, 30/60 dias" required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mb-4">
            <label for="type" class="form-label">Tipo *</label>
            <select name="type" id="type" class="form-control @error('type') is-invalid @enderror" required>
                <option value="cash" {{ old('type', $paymentCondition->type) === 'cash' ? 'selected' : '' }}>À Vista</option>
                <option value="installments" {{ old('type', $paymentCondition->type) === 'installments' ? 'selected' : '' }}>Parcelado</option>
                <option value="custom" {{ old('type', $paymentCondition->type) === 'custom' ? 'selected' : '' }}>Personalizado</option>
            </select>
            @error('type')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="flex gap-4">
            <div class="form-group mb-4 flex-1">
                <label for="installments_count" class="form-label">Quantidade de Parcelas *</label>
                <input type="number" name="installments_count" id="installments_count" class="form-control @error('installments_count') is-invalid @enderror" value="{{ old('installments_count', $paymentCondition->installments_count) }}" min="1" required>
                @error('installments_count')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-4 flex-1">
                <label for="interval_days" class="form-label">Intervalo de Dias *</label>
                <input type="number" name="interval_days" id="interval_days" class="form-control @error('interval_days') is-invalid @enderror" value="{{ old('interval_days', $paymentCondition->interval_days) }}" min="0" required>
                @error('interval_days')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-group mb-4">
            <label for="default_payment_method" class="form-label">Forma de Pagamento Padrão (Opcional)</label>
            <select name="default_payment_method" id="default_payment_method" class="form-control @error('default_payment_method') is-invalid @enderror">
                <option value="">Nenhuma</option>
                @foreach($methods as $method)
                    <option value="{{ $method->value }}" {{ old('default_payment_method', $paymentCondition->default_payment_method) === $method->value ? 'selected' : '' }}>{{ $method->label() }}</option>
                @endforeach
            </select>
            @error('default_payment_method')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mb-4">
            <label for="default_financial_account_id" class="form-label">Conta Financeira de Destino Padrão (Opcional)</label>
            <select name="default_financial_account_id" id="default_financial_account_id" class="form-control @error('default_financial_account_id') is-invalid @enderror">
                <option value="">Nenhuma</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" {{ old('default_financial_account_id', $paymentCondition->default_financial_account_id) == $acc->id ? 'selected' : '' }}>{{ $acc->name }}</option>
                @endforeach
            </select>
            @error('default_financial_account_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mb-4 flex items-center gap-2">
            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $paymentCondition->is_active) ? 'checked' : '' }}>
            <label for="is_active" class="form-label" style="margin-bottom:0">Ativa</label>
        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="{{ route('payment-conditions.index') }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection
