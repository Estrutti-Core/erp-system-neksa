<?php

namespace App\Http\Requests;

use App\Enums\FiscalOrigin;
use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autenticação controlada por middleware
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? $this->route('product');

        return [
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'sku'                 => ['required', 'string', 'max:50', 'unique:products,sku,' . ($productId ?? 'NULL') . ',id'],
            'barcode'             => ['nullable', 'string', 'max:50'],
            
            // Fiscal
            'ncm'                 => ['nullable', 'string', 'max:10'],
            'cfop'                => ['nullable', 'string', 'max:5'],
            'cst'                 => ['nullable', 'string', 'max:5'],
            'csosn'               => ['nullable', 'string', 'max:5'],
            'fiscal_origin'       => ['required', new Enum(FiscalOrigin::class)],
            'commercial_unit'     => ['required', 'string', 'max:10'],
            'taxable_unit'        => ['required', 'string', 'max:10'],
            
            // Comercial
            'cost_price'          => ['required', 'numeric', 'min:0'],
            'sale_price'          => ['required', 'numeric', 'min:0'],
            'stock'               => ['nullable', 'numeric'],
            'is_active'           => ['nullable', 'boolean'],
            
            // Operacional
            'type'                => ['required', new Enum(ProductType::class)],
            'is_stock_controlled' => ['nullable', 'boolean'],
            'internal_notes'      => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'            => 'O nome do produto é obrigatório.',
            'sku.required'             => 'O SKU/Código Interno é obrigatório.',
            'sku.unique'               => 'Este SKU já está sendo utilizado por outro produto.',
            'fiscal_origin.required'   => 'A Origem Fiscal é obrigatória.',
            'commercial_unit.required' => 'A Unidade Comercial é obrigatória.',
            'taxable_unit.required'    => 'A Unidade Tributável é obrigatória.',
            'cost_price.required'      => 'O Preço de Custo é obrigatório.',
            'sale_price.required'      => 'O Preço de Venda é obrigatório.',
            'type.required'            => 'O Tipo (Produto ou Serviço) é obrigatório.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Tratar checkbox values que vêm vazios ou ausentes do HTML
        $this->merge([
            'is_active'           => $this->has('is_active'),
            'is_stock_controlled' => $this->has('is_stock_controlled'),
            // Converter preços de formato BR (ex: "1.250,50" -> 1250.50) se necessário
            'cost_price'          => $this->cost_price ? (float) str_replace(',', '.', str_replace('.', '', $this->cost_price)) : 0,
            'sale_price'          => $this->sale_price ? (float) str_replace(',', '.', str_replace('.', '', $this->sale_price)) : 0,
            'stock'               => $this->stock ? (float) str_replace(',', '.', str_replace('.', '', $this->stock)) : null,
        ]);
    }
}
