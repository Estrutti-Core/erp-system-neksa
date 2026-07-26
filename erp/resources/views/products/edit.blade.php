@extends('layouts.app')

@section('title', 'Editar Item')

@section('topbar-actions')
    @can('delete', $product)
        <button type="button" class="btn btn-danger" onclick="if(confirm('Tem certeza que deseja excluir permanentemente este item?')) document.getElementById('delete-form').submit();">
            <x-heroicon-o-trash class="w-4 h-4"/> Excluir
        </button>
    @endcan
    <a href="{{ route('products.index') }}" class="btn btn-secondary" style="border-radius: 8px;">Cancelar</a>
    <button type="submit" form="product-form" class="btn btn-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); border-radius: 8px;">
        Salvar Alterações
    </button>
@endsection

@section('content')
<div style="max-width: 900px; margin: 0 auto; padding-bottom: 40px;">
    <!-- Cabeçalho de Ações -->
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('products.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>
        <h2 style="font-size: 18px; font-weight: 700; color: #0f172a;">Editar Item: {{ $product->name }}</h2>
    </div>

    <!-- Formulário Principal -->
    <form method="POST" action="{{ route('products.update', $product) }}" id="product-form">
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
                    <label class="form-label" for="name">Nome do Item <span style="color:#ef4444">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $product->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Ex: Câmera Intelbras 1220B" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="type">Tipo do Item <span style="color:#ef4444">*</span></label>
                    <select name="type" id="type" class="form-control" onchange="toggleInventoryControl(this.value)" required>
                        @foreach($productTypes as $type)
                            <option value="{{ $type->value }}" {{ old('type', $product->type->value) === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid-2 mb-3">
                <div class="form-group">
                    <label class="form-label" for="sku">SKU / Código Interno <span style="color:#ef4444">*</span></label>
                    <input type="text" name="sku" id="sku" value="{{ old('sku', $product->sku) }}" class="form-control @error('sku') is-invalid @enderror" placeholder="Ex: CAM-1220B" required>
                    @error('sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-group" id="barcode-group">
                    <label class="form-label" for="barcode">Código de Barras (EAN)</label>
                    <input type="text" name="barcode" id="barcode" value="{{ old('barcode', $product->barcode) }}" class="form-control" placeholder="Opcional">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Descrição Completa</label>
                <textarea name="description" id="description" class="form-control" rows="3" placeholder="Informações públicas/comerciais sobre o item...">{{ old('description', $product->description) }}</textarea>
            </div>
        </div>

        <!-- 02. Dados Comerciais & Estoque -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">2</div>
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Comercial & Estoque</h3>
            </div>

            <div class="grid-2 mb-3">
                <div class="form-group" id="cost-price-group">
                    <label class="form-label" for="cost_price">Preço de Custo (R$) <span style="color:#ef4444">*</span></label>
                    <input type="text" name="cost_price" id="cost_price" value="{{ old('cost_price', number_format($product->cost_price, 2, ',', '.')) }}" class="form-control money" placeholder="0,00" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sale_price">Preço de Venda (R$) <span style="color:#ef4444">*</span></label>
                    <input type="text" name="sale_price" id="sale_price" value="{{ old('sale_price', number_format($product->sale_price, 2, ',', '.')) }}" class="form-control money" placeholder="0,00" required>
                </div>
            </div>

            <div id="inventory-section">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                    <div class="flex items-center gap-2 mb-3">
                        <input type="checkbox" name="is_stock_controlled" id="is_stock_controlled" value="1" {{ old('is_stock_controlled', $product->is_stock_controlled) ? 'checked' : '' }} onchange="toggleStockField(this.checked)">
                        <label for="is_stock_controlled" style="font-weight: 600; font-size: 13px; color: #334155; cursor: pointer;">Controlar estoque deste produto</label>
                    </div>

                    <div class="form-group" id="stock-field-group" style="display: {{ old('is_stock_controlled', $product->is_stock_controlled) ? 'block' : 'none' }}; margin-bottom: 0;">
                        <label class="form-label" for="stock">Estoque Físico</label>
                        <input type="text" name="stock" id="stock" value="{{ old('stock', $product->stock ? number_format($product->stock, 0, ',', '.') : '') }}" class="form-control" placeholder="0" style="max-width: 200px;">
                        <span style="font-size: 11px; color: #64748b; margin-top: 4px; display: block;">Quantidade física disponível em mãos neste momento.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 03. Base Fiscal -->
        <div class="card mb-4 shadow-sm" id="fiscal-section" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">3</div>
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Tributação & Fiscal</h3>
            </div>

            <div class="form-group mb-3">
                <label class="form-label" for="fiscal_origin">Origem Fiscal da Mercadoria <span style="color:#ef4444">*</span></label>
                <select name="fiscal_origin" id="fiscal_origin" class="form-control" required>
                    @foreach($fiscalOrigins as $origin)
                        <option value="{{ $origin->value }}" {{ old('fiscal_origin', $product->fiscal_origin->value) == $origin->value ? 'selected' : '' }}>{{ $origin->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid-3 mb-3">
                <div class="form-group">
                    <label class="form-label" for="ncm">NCM</label>
                    <input type="text" name="ncm" id="ncm" value="{{ old('ncm', $product->ncm) }}" class="form-control" placeholder="Ex: 85258913">
                </div>

                <div class="form-group">
                    <label class="form-label" for="cfop">CFOP Padrão</label>
                    <input type="text" name="cfop" id="cfop" value="{{ old('cfop', $product->cfop) }}" class="form-control" placeholder="Ex: 5102">
                </div>

                <div class="form-group" id="unit-group-com">
                    <label class="form-label" for="commercial_unit">Unid. Comercial <span style="color:#ef4444">*</span></label>
                    <input type="text" name="commercial_unit" id="commercial_unit" value="{{ old('commercial_unit', $product->commercial_unit) }}" class="form-control" placeholder="Ex: UN" required>
                </div>
            </div>

            <div class="grid-3 mb-3">
                <div class="form-group">
                    <label class="form-label" for="cst">CST (Regime Normal)</label>
                    <input type="text" name="cst" id="cst" value="{{ old('cst', $product->cst) }}" class="form-control" placeholder="Ex: 00">
                </div>

                <div class="form-group">
                    <label class="form-label" for="csosn">CSOSN (Simples Nacional)</label>
                    <input type="text" name="csosn" id="csosn" value="{{ old('csosn', $product->csosn) }}" class="form-control" placeholder="Ex: 102">
                </div>

                <div class="form-group" id="unit-group-trib">
                    <label class="form-label" for="taxable_unit">Unid. Tributável <span style="color:#ef4444">*</span></label>
                    <input type="text" name="taxable_unit" id="taxable_unit" value="{{ old('taxable_unit', $product->taxable_unit) }}" class="form-control" placeholder="Ex: UN" required>
                </div>
            </div>
        </div>

        <!-- 04. Observações e Status -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="width: 28px; height: 28px; border-radius: 6px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">4</div>
                <h3 style="font-size: 15px; font-weight: 700; color: #1e293b;">Parâmetros Internos</h3>
            </div>

            <div class="form-group mb-3">
                <label class="form-label" for="internal_notes">Observações Internas (Técnicas/Operacionais)</label>
                <textarea name="internal_notes" id="internal_notes" class="form-control" rows="2" placeholder="Informações de suporte técnico ou restrições internas...">{{ old('internal_notes', $product->internal_notes) }}</textarea>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }}>
                <label for="is_active" style="font-weight: 600; font-size: 13px; color: #334155; cursor: pointer;">Este item está Ativo e disponível para uso</label>
            </div>
        </div>

    </form>

    <!-- Formulário Invisível de Exclusão -->
    @can('delete', $product)
        <form id="delete-form" method="POST" action="{{ route('products.destroy', $product) }}" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    @endcan
</div>
@endsection

@push('scripts')
<script>
    function toggleInventoryControl(type) {
        const inventorySection = document.getElementById('inventory-section');
        const fiscalSection = document.getElementById('fiscal-section');
        const costPriceGroup = document.getElementById('cost-price-group');
        const barcodeGroup = document.getElementById('barcode-group');
        
        const unitCom = document.getElementById('commercial_unit');
        const unitTrib = document.getElementById('taxable_unit');
        const fiscalOrigin = document.getElementById('fiscal_origin');
        const costPrice = document.getElementById('cost_price');
        
        if (type === 'service') {
            inventorySection.style.display = 'none';
            fiscalSection.style.display = 'none';
            costPriceGroup.style.display = 'none';
            barcodeGroup.style.display = 'none';
            
            document.getElementById('is_stock_controlled').checked = false;
            toggleStockField(false);
            unitCom.value = 'SV';
            unitTrib.value = 'SV';
            fiscalOrigin.value = '0';
            if (!costPrice.value || costPrice.value === '0,00') {
                costPrice.value = '0,00';
            }
        } else {
            inventorySection.style.display = 'block';
            fiscalSection.style.display = 'block';
            costPriceGroup.style.display = 'block';
            barcodeGroup.style.display = 'block';
            
            if (unitCom.value === 'SV') unitCom.value = 'UN';
            if (unitTrib.value === 'SV') unitTrib.value = 'UN';
        }
    }

    function toggleStockField(checked) {
        const stockFieldGroup = document.getElementById('stock-field-group');
        if (checked) {
            stockFieldGroup.style.display = 'block';
            document.getElementById('stock').required = true;
        } else {
            stockFieldGroup.style.display = 'none';
            document.getElementById('stock').required = false;
            document.getElementById('stock').value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar com base no valor atual
        toggleInventoryControl(document.getElementById('type').value);
        toggleStockField(document.getElementById('is_stock_controlled').checked);

        // Auto-formatar campos monetários ao digitar
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
    });
</script>
@endpush
