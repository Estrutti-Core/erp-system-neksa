<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $serviceId = $this->route('service')?->id ?? $this->route('service');

        return [
            'name'                   => ['required', 'string', 'max:255'],
            'description'            => ['nullable', 'string'],
            'sku'                    => ['required', 'string', 'max:50', 'unique:services,sku,' . ($serviceId ?? 'NULL') . ',id'],
            'cfop'                   => ['nullable', 'string', 'max:5'],
            'cst'                    => ['nullable', 'string', 'max:5'],
            'iss_rate'               => ['required', 'numeric', 'min:0', 'max:100'],
            'iss_withheld'           => ['nullable', 'boolean'],
            'pis_retention_rate'     => ['required', 'numeric', 'min:0', 'max:100'],
            'cofins_retention_rate'  => ['required', 'numeric', 'min:0', 'max:100'],
            'csll_retention_rate'    => ['required', 'numeric', 'min:0', 'max:100'],
            'inss_retention_rate'    => ['required', 'numeric', 'min:0', 'max:100'],
            'municipal_service_code' => ['nullable', 'string', 'max:50'],
            'price'                  => ['required', 'numeric', 'min:0'],
            'is_active'              => ['nullable', 'boolean'],
            'checklist_templates'    => ['nullable', 'array'],
            'checklist_templates.*'  => ['exists:checklist_templates,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'O nome do serviço é obrigatório.',
            'sku.required'   => 'O SKU/Código Interno é obrigatório.',
            'sku.unique'     => 'Este SKU já está sendo utilizado por outro serviço.',
            'price.required' => 'O Preço do Serviço é obrigatório.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active'             => $this->has('is_active'),
            'iss_withheld'          => $this->has('iss_withheld'),
            'price'                 => $this->price ? (float) str_replace(',', '.', str_replace('.', '', $this->price)) : 0,
            'iss_rate'              => $this->iss_rate ? (float) str_replace(',', '.', str_replace('.', '', $this->iss_rate)) : 0,
            'pis_retention_rate'    => $this->pis_retention_rate ? (float) str_replace(',', '.', str_replace('.', '', $this->pis_retention_rate)) : 0,
            'cofins_retention_rate' => $this->cofins_retention_rate ? (float) str_replace(',', '.', str_replace('.', '', $this->cofins_retention_rate)) : 0,
            'csll_retention_rate'   => $this->csll_retention_rate ? (float) str_replace(',', '.', str_replace('.', '', $this->csll_retention_rate)) : 0,
            'inss_retention_rate'   => $this->inss_retention_rate ? (float) str_replace(',', '.', str_replace('.', '', $this->inss_retention_rate)) : 0,
        ]);
    }
}
