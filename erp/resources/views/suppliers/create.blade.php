@extends('layouts.app')
@section('title', 'Novo Fornecedor')

@section('content')
<div style="max-width: 700px; margin: 0 auto; padding-bottom: 40px;">
    <!-- Cabeçalho de Ações -->
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('suppliers.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>
        <h2 style="font-size: 18px; font-weight: 700; color: #0f172a;">Cadastrar Novo Fornecedor</h2>
    </div>

    <form method="POST" action="{{ route('suppliers.store') }}" id="supplier-form">
        @csrf

        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div class="grid-2 mb-3">
                <div class="form-group">
                    <label class="form-label" for="document_type">Tipo de Pessoa</label>
                    <select id="document_type" name="document_type" class="form-control">
                        <option value="cnpj" {{ old('document_type', 'cnpj') == 'cnpj' ? 'selected' : '' }}>Pessoa Jurídica (CNPJ)</option>
                        <option value="cpf" {{ old('document_type') == 'cpf' ? 'selected' : '' }}>Pessoa Física (CPF)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="document">CPF / CNPJ</label>
                    <input type="text" id="document" name="document" value="{{ old('document') }}" class="form-control" placeholder="Digite o documento">
                    <div id="cnpj-loading-indicator" style="display: none; font-size: 11px; color: #4f46e5; margin-top: 4px; font-weight: 600;">
                        <span class="animate-pulse">Buscando dados do CNPJ...</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Razão Social / Nome <span style="color:#ef4444">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Ex: Distribuidora de Peças Ltda" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="phone">Telefone de Contato</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone') }}" class="form-control phone-mask" placeholder="(00) 00000-0000">
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="fornecedor@email.com">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <!-- Ações do Formulário -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('suppliers.index') }}" class="btn btn-secondary" style="border-radius: 8px;">Cancelar</a>
            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); border-radius: 8px; padding: 10px 24px;">
                Salvar Fornecedor
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    let docMask = null;

    function initMasks() {
        const docInput = document.getElementById('document');
        if (docInput) {
            const docValue = docInput.value.replace(/\D/g, '');
            let maskPattern = '00.000.000/0000-00';
            if (document.getElementById('document_type').value === 'cpf') {
                maskPattern = '000.000.000-00';
            }

            if (docMask) docMask.destroy();
            docMask = IMask(docInput, { mask: maskPattern });
        }

        document.querySelectorAll('.phone-mask').forEach(el => {
            if (!el.classList.contains('masked')) {
                IMask(el, {
                    mask: [
                        { mask: '(00) 0000-0000' },
                        { mask: '(00) 00000-0000' }
                    ]
                });
                el.classList.add('masked');
            }
        });
    }

    let lastQueriedCnpj = '';
    function runCnpjLookup(cnpj) {
        if (cnpj.length !== 14 || cnpj === lastQueriedCnpj) return;
        lastQueriedCnpj = cnpj;

        const indicator = document.getElementById('cnpj-loading-indicator');
        if (indicator) indicator.style.display = 'block';

        fetch(`/clients/cnpj/${cnpj}`)
            .then(r => {
                if (!r.ok) throw new Error();
                return r.json();
            })
            .then(d => {
                if (indicator) indicator.style.display = 'none';
                document.getElementById('name').value = d.social_name || d.trade_name || '';
                
                if (d.phone) {
                    document.getElementById('phone').value = d.phone;
                    document.getElementById('phone').dispatchEvent(new Event('input'));
                }
                if (d.email) document.getElementById('email').value = d.email;
            })
            .catch(() => {
                if (indicator) indicator.style.display = 'none';
            });
    }

    document.getElementById('document')?.addEventListener('input', function() {
        const type = document.getElementById('document_type').value;
        const val = this.value.replace(/\D/g, '');
        if (type === 'cnpj' && val.length === 14) {
            runCnpjLookup(val);
        }
    });

    document.getElementById('document_type').addEventListener('change', function() {
        const docInput = document.getElementById('document');
        if (this.value === 'cnpj') {
            docInput.placeholder = '00.000.000/0000-00';
        } else {
            docInput.placeholder = '000.000.000-00';
        }
        initMasks();
    });

    document.addEventListener('DOMContentLoaded', function() {
        initMasks();
    });
</script>
@endpush
