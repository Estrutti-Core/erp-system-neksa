@extends('layouts.app')
@section('title', 'Configurações da Empresa')

@section('content')
<div class="card max-w-3xl mx-auto">
    <h2 class="font-bold mb-4 flex items-center gap-2" style="font-size:16px"><x-heroicon-o-building-office-2 class="w-6 h-6 text-indigo-600"/> Dados do ERP</h2>

    <form action="{{ route('company.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="grid-2 mb-4">
            <div class="form-group">
                <label class="form-label">Nome da Empresa</label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $company->name) }}" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            
            <div class="form-group">
                <label class="form-label">CNPJ / Documento</label>
                <input type="text" id="document" name="document" class="form-control" value="{{ old('document', $company->document) }}" data-mask="document">
            </div>
            
            <div class="form-group">
                <label class="form-label">Telefone de Contato</label>
                <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone', $company->phone) }}" data-mask="phone">
            </div>

            <div class="form-group">
                <label class="form-label">E-mail de Contato</label>
                <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $company->email) }}">
            </div>
            
            <div class="form-group" style="grid-column: span 2">
                <label class="form-label">Endereço Completo</label>
                <input type="text" id="address" name="address" class="form-control" value="{{ old('address', $company->address) }}">
            </div>
        </div>

        <hr class="mb-4" style="border:0; border-top:1px solid #e2e8f0;">

        <div class="mb-4">
            <label class="form-label">Logo do Sistema (PNG/JPG)</label>
            <div class="flex items-center gap-4">
                @if($company->logo_path)
                    <div style="background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                        <img src="{{ asset('storage/' . $company->logo_path) }}" style="max-height: 60px;">
                    </div>
                @endif
                <input type="file" name="logo" class="form-control" accept="image/*">
            </div>
            @error('logo') <div class="invalid-feedback mt-2">{{ $message }}</div> @enderror
        </div>

        <hr class="mb-4" style="border:0; border-top:1px solid #e2e8f0;">

        <div class="mb-4">
            <div class="flex items-center gap-2">
                <input type="checkbox" id="allow_negative_stock" name="allow_negative_stock" value="1" style="width:18px; height:18px; cursor:pointer;" {{ old('allow_negative_stock', $company->allow_negative_stock) ? 'checked' : '' }}>
                <label for="allow_negative_stock" class="form-label mb-0" style="font-weight:600; cursor:pointer;">Permitir Estoque Negativo</label>
            </div>
            <p class="text-xs text-gray-500 mt-1" style="color:#64748b; font-size:12px; margin-left: 26px;">
                Se ativado, o sistema permitirá saídas de estoque mesmo se o saldo atual do produto for insuficiente. Recomendado apenas para ajustes operacionais flexíveis.
            </p>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <a href="{{ route('dashboard') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><x-heroicon-o-check class="w-4 h-4"/> Salvar Configurações</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
// Auto-preencher dados pelo CNPJ (BrasilAPI)
document.getElementById('document')?.addEventListener('blur', function() {
    const val = this.value.replace(/\D/g, '');
    if (val.length === 14) {
        const originalPlaceholder = this.placeholder;
        this.placeholder = 'Buscando CNPJ...';
        
        fetch(`https://brasilapi.com.br/api/cnpj/v1/${val}`)
            .then(r => {
                if (!r.ok) throw new Error();
                return r.json();
            })
            .then(d => {
                this.placeholder = originalPlaceholder;
                
                const nameEl = document.getElementById('name');
                if (nameEl && !nameEl.value) {
                    nameEl.value = d.razao_social || d.nome_fantasia || '';
                }
                
                const phoneEl = document.getElementById('phone');
                if (phoneEl && !phoneEl.value && d.ddd && d.telefone) {
                    phoneEl.value = `(${d.ddd}) ${d.telefone}`;
                    phoneEl.dispatchEvent(new Event('input'));
                }
                
                const emailEl = document.getElementById('email');
                if (emailEl && !emailEl.value) {
                    emailEl.value = d.email || '';
                }
                
                const addrEl = document.getElementById('address');
                if (addrEl && !addrEl.value) {
                    let fullAddr = `${d.logradouro || ''}`;
                    if (d.numero) fullAddr += `, ${d.numero}`;
                    if (d.complemento) fullAddr += ` - ${d.complemento}`;
                    if (d.bairro) fullAddr += ` - ${d.bairro}`;
                    if (d.municipio) fullAddr += ` - ${d.municipio}/${d.uf || ''}`;
                    if (d.cep) fullAddr += ` (CEP: ${d.cep})`;
                    addrEl.value = fullAddr.trim();
                }
            })
            .catch(() => {
                this.placeholder = originalPlaceholder;
            });
    }
});
</script>
@endpush
@endsection
