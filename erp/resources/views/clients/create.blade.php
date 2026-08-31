@extends('layouts.app')
@section('title', 'Novo Cliente')

@section('content')
<style>
.fixed-actions-bar {
    position: fixed;
    bottom: 0;
    left: var(--sidebar-width, 260px);
    right: 0;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(8px);
    border-top: 1px solid #e2e8f0;
    padding: 16px 24px;
    z-index: 35;
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.05);
    transition: left .25s ease;
}

body.sidebar-collapsed-active .fixed-actions-bar {
    left: var(--sidebar-collapsed-width, 70px);
}

@media (max-width: 768px) {
    .fixed-actions-bar {
        left: 0;
        bottom: 60px; /* Aligned above bottom-nav */
        padding: 12px 16px;
        background: #fff;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.08);
    }
}
</style>
<div style="max-width: 900px; margin: 0 auto; padding-bottom: 120px;">
    <!-- Cabeçalho de Ações -->
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('clients.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>
        <h2 style="font-size: 18px; font-weight: 700; color: #0f172a;">Cadastrar Novo Cliente</h2>
    </div>

    <form method="POST" action="{{ route('clients.store') }}" id="client-form">
        @csrf

        <!-- 01. Identificação Básica -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #ede9fe; color: #6d28d9; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">1</div>
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Identificação Básica</h3>
            </div>

            <div class="grid-2 mb-3">
                <div class="form-group">
                    <label class="form-label" for="document_type">Tipo de Pessoa <span style="color:#ef4444">*</span></label>
                    <select id="document_type" name="document_type" class="form-control" onchange="togglePjFields(this.value)" required>
                        <option value="cpf" {{ old('document_type', 'cpf') == 'cpf' ? 'selected' : '' }}>Pessoa Física (CPF)</option>
                        <option value="cnpj" {{ old('document_type') == 'cnpj' ? 'selected' : '' }}>Pessoa Jurídica (CNPJ)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="document">CPF / CNPJ <span style="color:#ef4444">*</span></label>
                    <input type="text" id="document" name="document" value="{{ old('document') }}" class="form-control" placeholder="Digite o documento" required>
                    <div id="cnpj-loading-indicator" style="display: none; font-size: 11px; color: #4f46e5; margin-top: 4px; font-weight: 600;">
                        <span class="animate-pulse">Buscando dados do CNPJ...</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Nome Completo / Nome Fantasia <span style="color:#ef4444">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Ex: João da Silva ou Neksa Tecnologia" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="phone">Telefone Principal</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone') }}" class="form-control phone-mask" placeholder="(00) 00000-0000">
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone_secondary">Telefone Secundário</label>
                    <input type="text" id="phone_secondary" name="phone_secondary" value="{{ old('phone_secondary') }}" class="form-control phone-mask" placeholder="Opcional">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">E-mail de Cobrança / Contato</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="email@exemplo.com">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">Observações Internas (Restrições/Mural)</label>
                <textarea id="notes" name="notes" rows="2" class="form-control" placeholder="Notas operacionais internas...">{{ old('notes') }}</textarea>
            </div>
        </div>

        <!-- 02. Dados Corporativos (Apenas para CNPJ) -->
        <div class="card mb-4 shadow-sm" id="pj-info-section" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; display: none;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">2</div>
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Dados da Receita Federal (PJ)</h3>
            </div>

            <div class="grid-2 mb-3">
                <div class="form-group">
                    <label class="form-label" for="social_name">Razão Social</label>
                    <input type="text" id="social_name" name="social_name" value="{{ old('social_name') }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="trade_name">Nome Fantasia da Receita</label>
                    <input type="text" id="trade_name" name="trade_name" value="{{ old('trade_name') }}" class="form-control">
                </div>
            </div>

            <div class="grid-3 mb-3">
                <div class="form-group">
                    <label class="form-label" for="sector">Setor de Atividade</label>
                    <input type="text" id="sector" name="sector" value="{{ old('sector') }}" class="form-control" placeholder="Ex: Tecnologia">
                </div>
                <div class="form-group">
                    <label class="form-label" for="opening_date">Data de Abertura</label>
                    <input type="date" id="opening_date" name="opening_date" value="{{ old('opening_date') }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="capital_social">Capital Social (R$)</label>
                    <input type="text" id="capital_social" name="capital_social" value="{{ old('capital_social') }}" class="form-control money" placeholder="0,00">
                </div>
            </div>

            <div class="grid-3 mb-4">
                <div class="form-group">
                    <label class="form-label" for="company_size">Porte da Empresa</label>
                    <input type="text" id="company_size" name="company_size" value="{{ old('company_size') }}" class="form-control" placeholder="Ex: ME, EPP">
                </div>
                <div class="form-group">
                    <label class="form-label" for="legal_nature">Natureza Jurídica</label>
                    <input type="text" id="legal_nature" name="legal_nature" value="{{ old('legal_nature') }}" class="form-control" placeholder="Ex: LTDA">
                </div>
                <div class="form-group">
                    <label class="form-label" for="registration_status">Situação Cadastral</label>
                    <input type="text" id="registration_status" name="registration_status" value="{{ old('registration_status') }}" class="form-control" placeholder="Ex: ATIVA">
                </div>
            </div>

            <!-- CNAE Principal -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <h4 style="font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 8px;">CNAE Principal</h4>
                <div class="grid-3" style="grid-template-columns: 1fr 2fr; gap: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 11px;">Código</label>
                        <input type="text" id="main_cnae_code" name="main_cnae_code" value="{{ old('main_cnae_code') }}" class="form-control" placeholder="Ex: 6202300" style="font-family: monospace;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 11px;">Descrição da Atividade</label>
                        <input type="text" id="main_cnae_description" name="main_cnae_description" value="{{ old('main_cnae_description') }}" class="form-control" placeholder="Descrição do CNAE">
                    </div>
                </div>
            </div>

            <!-- CNAEs Secundários -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h4 style="font-size: 13px; font-weight: 700; color: #475569;">CNAEs Secundários</h4>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addSecondaryCnaeRow()">
                        <x-heroicon-o-plus class="w-3.5 h-3.5"/> Adicionar CNAE
                    </button>
                </div>
                <div id="secondary-cnae-container" class="flex flex-col gap-2">
                    <!-- Gerado dinamicamente -->
                </div>
            </div>
        </div>

        <!-- 03. Aba de Contatos (PJ ou PF) -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; justify-content: between; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 28px; height: 28px; border-radius: 6px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">3</div>
                    <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Contatos do Cliente</h3>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addContactRow()">
                    <x-heroicon-o-user-plus class="w-4 h-4"/> Novo Contato
                </button>
            </div>

            <div id="contacts-container" class="flex flex-col gap-4">
                <!-- Gerado dinamicamente -->
            </div>
        </div>

        <!-- 04. Equipamentos do Cliente -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 28px; height: 28px; border-radius: 6px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">4</div>
                    <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Equipamentos do Cliente</h3>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addEquipmentRow()">
                    <x-heroicon-o-cpu-chip class="w-4 h-4"/> Novo Equipamento
                </button>
            </div>

            <div id="equipments-container" class="flex flex-col gap-4">
                <!-- Gerado dinamicamente -->
            </div>
        </div>

        <!-- 05. Endereço Principal -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">5</div>
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Endereço de Atendimento</h3>
            </div>

            <div class="grid-2 mb-3">
                <div class="form-group">
                    <label class="form-label" for="zip_code">CEP</label>
                    <input type="text" id="zip_code" name="zip_code" value="{{ old('zip_code') }}" class="form-control cep-mask" placeholder="00000-000">
                </div>
                <div></div>
            </div>

            <div class="grid-2 mb-3">
                <div class="form-group">
                    <label class="form-label" for="street">Logradouro *</label>
                    <input type="text" id="street" name="street" value="{{ old('street') }}" class="form-control @error('street') is-invalid @enderror" placeholder="Rua, Avenida..." required>
                    @error('street')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="number">Número</label>
                    <input type="text" id="number" name="number" value="{{ old('number') }}" class="form-control" placeholder="123, S/N">
                </div>
            </div>

            <div class="grid-2 mb-3">
                <div class="form-group">
                    <label class="form-label" for="complement">Complemento</label>
                    <input type="text" id="complement" name="complement" value="{{ old('complement') }}" class="form-control" placeholder="Apto, Sala, Bloco...">
                </div>
                <div class="form-group">
                    <label class="form-label" for="neighborhood">Bairro</label>
                    <input type="text" id="neighborhood" name="neighborhood" value="{{ old('neighborhood') }}" class="form-control">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="city">Cidade *</label>
                    <input type="text" id="city" name="city" value="{{ old('city') }}" class="form-control @error('city') is-invalid @enderror" required>
                    @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="state">Estado *</label>
                    <select id="state" name="state" class="form-control @error('state') is-invalid @enderror" required>
                        <option value="">UF</option>
                        @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf)
                            <option value="{{ $uf }}" {{ old('state') == $uf ? 'selected' : '' }}>{{ $uf }}</option>
                        @endforeach
                    </select>
                    @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <!-- Ações do Formulário (Floating Bar) -->
        <div class="fixed-actions-bar">
            <div style="max-width: 900px; margin: 0 auto; width: 100%; display: flex; justify-content: flex-end; gap: 12px; align-items: center;">
                <a href="{{ route('clients.index') }}" class="btn btn-secondary" style="border-radius: 8px;">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); border-radius: 8px; padding: 10px 24px;">
                    Salvar Cadastro
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    let cnaeIndex = 0;
    let contactIndex = 0;
    let equipmentIndex = 0;

    const BRANDS_MODELS = {
        'Samsung': ['WindFree', 'Dual Inverter', 'Max Plus', 'Galaxy Book', 'Smart Inverter'],
        'LG': ['Dual Inverter', 'Artcool', 'Smart Inverter', 'ThinQ', 'Gram'],
        'Google': ['Gemini', 'Nest Hub', 'Pixel Server', 'Chromecast', 'Pixelbook'],
        'Dell': ['PowerEdge', 'OptiPlex', 'Latitude', 'Inspiron', 'Precision', 'Vostro'],
        'HP': ['ProLiant', 'LaserJet', 'EliteBook', 'Pavilion', 'ProBook', 'Smart Tank'],
        'Apple': ['MacBook Pro', 'MacBook Air', 'iMac', 'Mac mini', 'Mac Studio', 'iPad Pro', 'iPhone Pro'],
        'Lenovo': ['ThinkPad', 'ThinkCentre', 'ThinkSystem', 'IdeaPad', 'Legion', 'Yoga'],
        'Cisco': ['Catalyst', 'Meraki', 'ASA Firewall', 'ISR Router', 'Nexus'],
        'Ubiquiti': ['UniFi AP', 'UniFi Switch', 'EdgeRouter', 'UniFi Dream Machine', 'U6 Pro'],
        'MikroTik': ['hEX', 'Cloud Core Router (CCR)', 'Cloud Router Switch (CRS)', 'NetMetal', 'Chateau'],
        'Daikin': ['Fit', 'VRV', 'Inverter Split', 'Multi-Split'],
        'Carrier': ['XPower Inverter', 'Piso Teto', 'Cassete Inverter', '40KV'],
        'Gree': ['G-Prime', 'Eco Garden', 'Inverter Split', 'Eco Air']
    };

    function populateBrandsSelect(index) {
        const brandSelect = document.getElementById(`equip-brand-select-${index}`);
        brandSelect.innerHTML = '<option value="">Selecione a marca...</option>';
        Object.keys(BRANDS_MODELS).sort().forEach(brand => {
            const opt = document.createElement('option');
            opt.value = brand;
            opt.textContent = brand;
            brandSelect.appendChild(opt);
        });
        const optOutro = document.createElement('option');
        optOutro.value = 'outro';
        optOutro.textContent = 'Outro (especificar)...';
        brandSelect.appendChild(optOutro);
    }

    function handleBrandChange(index) {
        const brandSelect = document.getElementById(`equip-brand-select-${index}`);
        const brandInput = document.getElementById(`equip-brand-custom-${index}`);
        const modelSelect = document.getElementById(`equip-model-select-${index}`);
        const modelInput = document.getElementById(`equip-model-custom-${index}`);
        
        const selectedBrand = brandSelect.value;
        
        modelSelect.innerHTML = '<option value="">Selecione o modelo...</option>';
        modelInput.value = '';
        modelInput.classList.add('hidden');
        modelInput.required = false;
        
        if (selectedBrand === 'outro') {
            brandInput.value = '';
            brandInput.classList.remove('hidden');
            brandInput.required = true;
            
            const optOutro = document.createElement('option');
            optOutro.value = 'outro';
            optOutro.textContent = 'Outro (especificar)...';
            modelSelect.appendChild(optOutro);
            modelSelect.value = 'outro';
            modelInput.classList.remove('hidden');
            modelInput.required = true;
        } else if (selectedBrand) {
            brandInput.value = selectedBrand;
            brandInput.classList.add('hidden');
            brandInput.required = false;
            
            const models = BRANDS_MODELS[selectedBrand] || [];
            models.forEach(model => {
                const opt = document.createElement('option');
                opt.value = model;
                opt.textContent = model;
                modelSelect.appendChild(opt);
            });
            const optOutro = document.createElement('option');
            optOutro.value = 'outro';
            optOutro.textContent = 'Outro (especificar)...';
            modelSelect.appendChild(optOutro);
        } else {
            brandInput.value = '';
            brandInput.classList.add('hidden');
            brandInput.required = false;
            modelSelect.innerHTML = '<option value="">Selecione a marca primeiro...</option>';
        }
    }

    function handleModelChange(index) {
        const modelSelect = document.getElementById(`equip-model-select-${index}`);
        const modelInput = document.getElementById(`equip-model-custom-${index}`);
        
        if (modelSelect.value === 'outro') {
            modelInput.value = '';
            modelInput.classList.remove('hidden');
            modelInput.required = true;
        } else {
            modelInput.value = modelSelect.value;
            modelInput.classList.add('hidden');
            modelInput.required = false;
        }
    }

    function initEquipmentRow(index, brand, model) {
        populateBrandsSelect(index);
        const brandSelect = document.getElementById(`equip-brand-select-${index}`);
        const brandInput = document.getElementById(`equip-brand-custom-${index}`);
        const modelSelect = document.getElementById(`equip-model-select-${index}`);
        const modelInput = document.getElementById(`equip-model-custom-${index}`);
        
        if (brand && BRANDS_MODELS[brand]) {
            brandSelect.value = brand;
            handleBrandChange(index);
            if (model && BRANDS_MODELS[brand].includes(model)) {
                modelSelect.value = model;
                handleModelChange(index);
            } else if (model) {
                modelSelect.value = 'outro';
                handleModelChange(index);
                modelInput.value = model;
            } else {
                modelSelect.value = '';
                handleModelChange(index);
            }
        } else if (brand) {
            brandSelect.value = 'outro';
            handleBrandChange(index);
            brandInput.value = brand;
            
            modelSelect.value = 'outro';
            handleModelChange(index);
            modelInput.value = model;
        } else {
            brandSelect.value = '';
            handleBrandChange(index);
        }
    }

    function addEquipmentRow(equipment = {}) {
        const container = document.getElementById('equipments-container');
        const card = document.createElement('div');
        card.className = 'card equipment-card shadow-sm';
        card.style.background = '#fafaf9';
        card.style.border = '1px solid #e7e5e4';
        card.style.borderRadius = '8px';
        card.style.padding = '16px';
        card.id = `equipment-card-${equipmentIndex}`;

        card.innerHTML = `
            <div class="flex items-center justify-between mb-3 pb-2" style="border-bottom: 1px dashed #e7e5e4;">
                <span style="font-weight: 700; font-size: 13px; color: #44403c;">Dados do Equipamento</span>
                <button type="button" class="btn btn-secondary btn-sm" style="color: #ef4444; border-color:#fee2e2; padding: 4px 8px;" onclick="removeEquipmentRow(${equipmentIndex})">
                    Remover
                </button>
            </div>

            <div class="grid-2 mb-3">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">Nome / Identificação *</label>
                    <input type="text" name="equipments[${equipmentIndex}][name]" value="${equipment.name || ''}" class="form-control" required placeholder="Ex: Ar Condicionado, Servidor...">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">Marca</label>
                    <select id="equip-brand-select-${equipmentIndex}" class="form-control" onchange="handleBrandChange(${equipmentIndex})">
                        <option value="">Selecione a marca...</option>
                    </select>
                    <input type="text" id="equip-brand-custom-${equipmentIndex}" name="equipments[${equipmentIndex}][brand]" value="${equipment.brand || ''}" class="form-control mt-2 hidden" placeholder="Digite a marca...">
                </div>
            </div>

            <div class="grid-2 mb-3">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">Modelo</label>
                    <select id="equip-model-select-${equipmentIndex}" class="form-control" onchange="handleModelChange(${equipmentIndex})">
                        <option value="">Selecione a marca primeiro...</option>
                    </select>
                    <input type="text" id="equip-model-custom-${equipmentIndex}" name="equipments[${equipmentIndex}][model]" value="${equipment.model || ''}" class="form-control mt-2 hidden" placeholder="Digite o modelo...">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">Número de Série</label>
                    <input type="text" name="equipments[${equipmentIndex}][serial_number]" value="${equipment.serial_number || ''}" class="form-control" placeholder="S/N">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="font-size:11px;">Observações/Detalhes Técnicos</label>
                <textarea name="equipments[${equipmentIndex}][notes]" rows="2" class="form-control" placeholder="Acessórios inclusos, especificações técnicas...">${equipment.notes || ''}</textarea>
            </div>
        `;

        container.appendChild(card);
        initEquipmentRow(equipmentIndex, equipment.brand || '', equipment.model || '');
        equipmentIndex++;
    }

    function removeEquipmentRow(index) {
        const card = document.getElementById(`equipment-card-${index}`);
        if (card) card.remove();
    }

    function togglePjFields(type) {
        const pjSection = document.getElementById('pj-info-section');
        const lookupBtn = document.getElementById('btn-cnpj-lookup');
        const docInput = document.getElementById('document');

        // Reset masks
        if (type === 'cnpj') {
            pjSection.style.display = 'block';
            lookupBtn.style.display = 'block';
            docInput.placeholder = '00.000.000/0000-00';
        } else {
            pjSection.style.display = 'none';
            lookupBtn.style.display = 'none';
            docInput.placeholder = '000.000.000-00';
        }
    }

    function addSecondaryCnaeRow(code = '', description = '') {
        const container = document.getElementById('secondary-cnae-container');
        const row = document.createElement('div');
        row.className = 'grid-3 secondary-cnae-row';
        row.style.gridTemplateColumns = '1fr 2fr auto';
        row.style.gap = '12px';
        row.style.alignItems = 'flex-end';
        row.id = `cnae-row-${cnaeIndex}`;

        row.innerHTML = `
            <div class="form-group" style="margin-bottom:0;">
                <input type="text" name="secondary_cnaes[${cnaeIndex}][code]" value="${code}" class="form-control" placeholder="Cód. CNAE" style="font-family: monospace;">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <input type="text" name="secondary_cnaes[${cnaeIndex}][description]" value="${description}" class="form-control" placeholder="Descrição do CNAE">
            </div>
            <button type="button" class="btn btn-secondary" style="color: #ef4444; border-color:#fee2e2" onclick="removeCnaeRow(${cnaeIndex})">
                <x-heroicon-o-trash class="w-4 h-4"/>
            </button>
        `;

        container.appendChild(row);
        cnaeIndex++;
    }

    function removeCnaeRow(index) {
        const row = document.getElementById(`cnae-row-${index}`);
        if (row) row.remove();
    }

    function addContactRow(contact = {}) {
        const container = document.getElementById('contacts-container');
        const card = document.createElement('div');
        card.className = 'card contact-card shadow-sm';
        card.style.background = '#f8fafc';
        card.style.border = '1px solid #e2e8f0';
        card.style.borderRadius = '8px';
        card.style.padding = '16px';
        card.id = `contact-card-${contactIndex}`;

        const isPrimaryChecked = contact.is_primary ? 'checked' : (contactIndex === 0 ? 'checked' : '');
        const isPhoneBlocked = contact.is_phone_blocked ? 'checked' : '';
        const isWhatsappBlocked = contact.is_whatsapp_blocked ? 'checked' : '';
        const isEmailBlocked = contact.is_email_blocked ? 'checked' : '';

        card.innerHTML = `
            <div class="flex items-center justify-between mb-3 pb-2" style="border-bottom: 1px dashed #e2e8f0;">
                <div class="flex items-center gap-2">
                    <input type="radio" name="contacts_primary_radio" id="primary-radio-${contactIndex}" value="${contactIndex}" ${isPrimaryChecked} onchange="setPrimaryContact(${contactIndex})">
                    <label for="primary-radio-${contactIndex}" style="font-weight: 700; font-size: 13px; color: #1e293b; cursor: pointer;">Contato Principal</label>
                    <input type="hidden" name="contacts[${contactIndex}][is_primary]" id="primary-hidden-${contactIndex}" value="${isPrimaryChecked ? '1' : '0'}">
                </div>
                <button type="button" class="btn btn-secondary btn-sm" style="color: #ef4444; border-color:#fee2e2; padding: 4px 8px;" onclick="removeContactRow(${contactIndex})">
                    Remover
                </button>
            </div>

            <div class="grid-3 mb-3">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">Nome *</label>
                    <input type="text" name="contacts[${contactIndex}][name]" value="${contact.name || ''}" class="form-control" required placeholder="Nome do contato">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">Cargo / Função</label>
                    <input type="text" name="contacts[${contactIndex}][role]" value="${contact.role || ''}" class="form-control" placeholder="Ex: Técnico, Gerente">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">E-mail</label>
                    <input type="email" name="contacts[${contactIndex}][email]" value="${contact.email || ''}" class="form-control" placeholder="email@contato.com">
                </div>
            </div>

            <div class="grid-2 mb-3">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">Telefone</label>
                    <input type="text" name="contacts[${contactIndex}][phone]" value="${contact.phone || ''}" class="form-control phone-mask" placeholder="Telefone">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">WhatsApp</label>
                    <input type="text" name="contacts[${contactIndex}][whatsapp]" value="${contact.whatsapp || ''}" class="form-control phone-mask" placeholder="WhatsApp">
                </div>
            </div>

            <!-- Bloqueios/Privacidade -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; margin-top: 10px;">
                <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; display:block; margin-bottom:8px;">Preferências de Privacidade</span>
                <div class="flex items-center gap-4 flex-wrap">
                    <div class="flex items-center gap-1.5">
                        <input type="checkbox" name="contacts[${contactIndex}][is_phone_blocked]" id="phone-blocked-${contactIndex}" value="1" ${isPhoneBlocked}>
                        <label for="phone-blocked-${contactIndex}" style="font-size:11px; font-weight:600; color:#475569; cursor:pointer;">Bloquear Ligação</label>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <input type="checkbox" name="contacts[${contactIndex}][is_whatsapp_blocked]" id="whatsapp-blocked-${contactIndex}" value="1" ${isWhatsappBlocked}>
                        <label for="whatsapp-blocked-${contactIndex}" style="font-size:11px; font-weight:600; color:#475569; cursor:pointer;">Bloquear WhatsApp</label>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <input type="checkbox" name="contacts[${contactIndex}][is_email_blocked]" id="email-blocked-${contactIndex}" value="1" ${isEmailBlocked}>
                        <label for="email-blocked-${contactIndex}" style="font-size:11px; font-weight:600; color:#475569; cursor:pointer;">Bloquear E-mail</label>
                    </div>
                </div>
            </div>
        `;

        container.appendChild(card);
        
        // Initialize masks on newly created fields
        initMasks();
        contactIndex++;
    }

    function removeContactRow(index) {
        const card = document.getElementById(`contact-card-${index}`);
        if (card) card.remove();
    }

    function setPrimaryContact(index) {
        // Clear all primary inputs
        for (let i = 0; i < contactIndex; i++) {
            const hiddenInput = document.getElementById(`primary-hidden-${i}`);
            if (hiddenInput) {
                hiddenInput.value = (i === index) ? '1' : '0';
            }
        }
    }

    // Auto-preencher endereço pelo CEP
    document.getElementById('zip_code').addEventListener('blur', function() {
        const cep = this.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(r => r.json())
            .then(d => {
                if (d.erro) return;
                document.getElementById('street').value = d.logradouro || '';
                document.getElementById('neighborhood').value = d.bairro || '';
                document.getElementById('city').value = d.localidade || '';
                document.getElementById('state').value = d.uf || '';
            }).catch(() => {});
    });

    let lastQueriedCnpj = '';
    function runCnpjLookup(cnpj) {
        if (cnpj.length !== 14 || cnpj === lastQueriedCnpj) return;
        lastQueriedCnpj = cnpj;

        const indicator = document.getElementById('cnpj-loading-indicator');
        if (indicator) indicator.style.display = 'block';

        fetch(`/clients/cnpj/${cnpj}`)
            .then(r => {
                if (!r.ok) throw new Error('Não foi possível obter os dados do CNPJ.');
                return r.json();
            })
            .then(d => {
                if (indicator) indicator.style.display = 'none';

                // Preencher campos PJ
                document.getElementById('name').value = d.trade_name || d.social_name || '';
                document.getElementById('social_name').value = d.social_name || '';
                document.getElementById('trade_name').value = d.trade_name || '';
                
                if (d.opening_date) document.getElementById('opening_date').value = d.opening_date;
                if (d.capital_social) {
                    document.getElementById('capital_social').value = Number(d.capital_social).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                }
                document.getElementById('company_size').value = d.company_size || '';
                document.getElementById('legal_nature').value = d.legal_nature || '';
                document.getElementById('registration_status').value = d.registration_status || '';

                if (d.phone) {
                    document.getElementById('phone').value = d.phone;
                    document.getElementById('phone').dispatchEvent(new Event('input'));
                }
                if (d.email) document.getElementById('email').value = d.email;

                // CNAE Principal
                if (d.main_cnae) {
                    document.getElementById('main_cnae_code').value = d.main_cnae.code || '';
                    document.getElementById('main_cnae_description').value = d.main_cnae.description || '';
                }

                // Limpar CNAEs Secundários e preencher os novos
                document.getElementById('secondary-cnae-container').innerHTML = '';
                if (d.secondary_cnaes && d.secondary_cnaes.length > 0) {
                    d.secondary_cnaes.forEach(c => {
                        addSecondaryCnaeRow(c.code, c.description);
                    });
                }

                // Preencher Endereço
                if (d.zip_code) {
                    document.getElementById('zip_code').value = d.zip_code;
                    document.getElementById('zip_code').dispatchEvent(new Event('input'));
                }
                if (d.street) document.getElementById('street').value = d.street;
                if (d.number) document.getElementById('number').value = d.number;
                if (d.complement) document.getElementById('complement').value = d.complement;
                if (d.neighborhood) document.getElementById('neighborhood').value = d.neighborhood;
                if (d.city) document.getElementById('city').value = d.city;
                if (d.state) document.getElementById('state').value = d.state;
            })
            .catch(err => {
                if (indicator) indicator.style.display = 'none';
                console.error(err);
            });
    }

    document.getElementById('document').addEventListener('input', function() {
        const type = document.getElementById('document_type').value;
        const val = this.value.replace(/\D/g, '');
        if (type === 'cnpj' && val.length === 14) {
            runCnpjLookup(val);
        }
    });

    // Masks handler
    let docMask = null;
    function initMasks() {
        // Document mask
        const docInput = document.getElementById('document');
        if (docInput) {
            const docValue = docInput.value.replace(/\D/g, '');
            let maskPattern = '000.000.000-00';
            if (docValue.length > 11 || document.getElementById('document_type').value === 'cnpj') {
                maskPattern = '00.000.000/0000-00';
            }

            if (docMask) docMask.destroy();
            docMask = IMask(docInput, { mask: maskPattern });
        }

        // Phone mask
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

        // CEP Mask
        document.querySelectorAll('.cep-mask').forEach(el => {
            if (!el.classList.contains('masked')) {
                IMask(el, { mask: '00000-000' });
                el.classList.add('masked');
            }
        });

        // Money Mask
        document.querySelectorAll('.money').forEach(el => {
            if (!el.classList.contains('masked')) {
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
                el.classList.add('masked');
            }
        });
    }

    document.getElementById('document_type').addEventListener('change', function() {
        togglePjFields(this.value);
        initMasks();
    });

    document.addEventListener('DOMContentLoaded', function() {
        togglePjFields(document.getElementById('document_type').value);
        
        // Add a default contact row
        addContactRow({
            name: '',
            is_primary: true
        });

        initMasks();
    });
</script>
@endpush
