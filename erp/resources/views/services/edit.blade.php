@extends('layouts.app')

@section('title', 'Editar Serviço')

@section('content')
<div style="max-width: 900px; margin: 0 auto; padding-bottom: 40px;">
    <!-- Cabeçalho de Ações -->
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('services.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>
        <h2 style="font-size: 18px; font-weight: 700; color: #0f172a;">Editar Serviço: {{ $service->name }}</h2>
    </div>

    <!-- Formulário Principal -->
    <form method="POST" action="{{ route('services.update', $service) }}" id="service-form">
        @csrf
        @method('PUT')

        <!-- 01. Identificação -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #ede9fe; color: #6d28d9; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">1</div>
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Identificação Básica</h3>
            </div>

            <div class="grid-2 mb-3">
                <div class="form-group">
                    <label class="form-label" for="name">Nome do Serviço <span style="color:#ef4444">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $service->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Ex: Manutenção Preventiva de Ar-Condicionado" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="sku">SKU / Código Interno <span style="color:#ef4444">*</span></label>
                    <input type="text" name="sku" id="sku" value="{{ old('sku', $service->sku) }}" class="form-control @error('sku') is-invalid @enderror" placeholder="Ex: SERV-MNT-AR" required>
                    @error('sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Descrição do Serviço</label>
                <textarea name="description" id="description" class="form-control" rows="3" placeholder="Descreva os escopos e procedimentos do serviço...">{{ old('description', $service->description) }}</textarea>
            </div>
        </div>

        <!-- 02. Preço -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">2</div>
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Preço de Venda</h3>
            </div>

            <div class="form-group" style="max-width: 300px;">
                <label class="form-label" for="price">Valor do Serviço (R$) <span style="color:#ef4444">*</span></label>
                <input type="text" name="price" id="price" value="{{ old('price', number_format($service->price, 2, ',', '')) }}" class="form-control money @error('price') is-invalid @enderror" placeholder="0,00" required>
                @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <!-- 03. Tributação & Fiscal -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">3</div>
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Tributação & Dados Fiscais</h3>
            </div>

            <div class="grid-3 mb-4">
                <div class="form-group">
                    <label class="form-label" for="cfop">CFOP</label>
                    <input type="text" name="cfop" id="cfop" value="{{ old('cfop', $service->cfop) }}" class="form-control" placeholder="Ex: 5933">
                </div>

                <div class="form-group">
                    <label class="form-label" for="cst">CST / CSOSN</label>
                    <input type="text" name="cst" id="cst" value="{{ old('cst', $service->cst) }}" class="form-control" placeholder="Ex: 01 ou 400">
                </div>

                <div class="form-group">
                    <label class="form-label" for="municipal_service_code">Cód. Serviço Municipal (LC 116)</label>
                    <input type="text" name="municipal_service_code" id="municipal_service_code" value="{{ old('municipal_service_code', $service->municipal_service_code) }}" class="form-control" placeholder="Ex: 14.01">
                </div>
            </div>

            <div class="grid-2 mb-4" style="background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <div class="form-group">
                    <label class="form-label" for="iss_rate">Alíquota ISS (%) <span style="color:#ef4444">*</span></label>
                    <input type="text" name="iss_rate" id="iss_rate" value="{{ old('iss_rate', number_format($service->iss_rate, 2, ',', '')) }}" class="form-control rate @error('iss_rate') is-invalid @enderror" required>
                    @error('iss_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 8px;">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="iss_withheld" id="iss_withheld" value="1" {{ old('iss_withheld', $service->iss_withheld) ? 'checked' : '' }}>
                        <label for="iss_withheld" style="font-weight: 600; font-size: 13px; color: #334155; cursor: pointer;">ISS Retido na Fonte</label>
                    </div>
                </div>
            </div>

            <h4 style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 12px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px;">Retenções na Fonte (Alíquotas %)</h4>
            <div class="grid-4">
                <div class="form-group">
                    <label class="form-label" for="pis_retention_rate">PIS (%) <span style="color:#ef4444">*</span></label>
                    <input type="text" name="pis_retention_rate" id="pis_retention_rate" value="{{ old('pis_retention_rate', number_format($service->pis_retention_rate, 2, ',', '')) }}" class="form-control rate" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="cofins_retention_rate">COFINS (%) <span style="color:#ef4444">*</span></label>
                    <input type="text" name="cofins_retention_rate" id="cofins_retention_rate" value="{{ old('cofins_retention_rate', number_format($service->cofins_retention_rate, 2, ',', '')) }}" class="form-control rate" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="csll_retention_rate">CSLL (%) <span style="color:#ef4444">*</span></label>
                    <input type="text" name="csll_retention_rate" id="csll_retention_rate" value="{{ old('csll_retention_rate', number_format($service->csll_retention_rate, 2, ',', '')) }}" class="form-control rate" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="inss_retention_rate">INSS (%) <span style="color:#ef4444">*</span></label>
                    <input type="text" name="inss_retention_rate" id="inss_retention_rate" value="{{ old('inss_retention_rate', number_format($service->inss_retention_rate, 2, ',', '')) }}" class="form-control rate" required>
                </div>
            </div>
        </div>

        <!-- 04. Status -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $service->is_active) ? 'checked' : '' }}>
                <label for="is_active" style="font-weight: 600; font-size: 13px; color: #334155; cursor: pointer;">Este serviço está Ativo e disponível para uso</label>
            </div>
        </div>

        <!-- Ações do Formulário -->
        <div class="flex items-center justify-between">
            <div>
                @can('delete', $service)
                    <button type="button" class="btn btn-secondary" style="color:#ef4444; border-color:#fee2e2" onclick="confirmDelete()">
                        <x-heroicon-o-trash class="w-4 h-4"/> Excluir Serviço
                    </button>
                @endcan
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('services.index') }}" class="btn btn-secondary" style="border-radius: 8px;">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border-radius: 8px; padding: 10px 24px;">
                    Salvar Alterações
                </button>
            </div>
        </div>
    </form>

    @can('delete', $service)
        <form id="delete-form" action="{{ route('services.destroy', $service) }}" method="POST" style="display:none">
            @csrf
            @method('DELETE')
        </form>
    @endcan
</div>
@endsection

@push('scripts')
<script>
    function confirmDelete() {
        if (confirm('Tem certeza que deseja excluir este serviço? Esta ação não pode ser desfeita.')) {
            document.getElementById('delete-form').submit();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Auto-formatar campos monetários
        document.querySelectorAll('.money').forEach(el => {
            IMask(el, {
                mask: 'num',
                blocks: {
                    num: {
                        mask: Number,
                        thousandsSeparator: '.',
                        radix: ',',
                        scale: 2,
                        signed: false,
                        padFractionalZeros: true,
                        normalizeZeros: true,
                    }
                }
            });
        });

        // Auto-formatar alíquotas/porcentagens
        document.querySelectorAll('.rate').forEach(el => {
            IMask(el, {
                mask: 'num',
                blocks: {
                    num: {
                        mask: Number,
                        thousandsSeparator: '.',
                        radix: ',',
                        scale: 2,
                        signed: false,
                        padFractionalZeros: true,
                        normalizeZeros: true,
                        min: 0,
                        max: 100
                    }
                }
            });
        });
    });
</script>
@endpush
