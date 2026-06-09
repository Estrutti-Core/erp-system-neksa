<?php

namespace App\Http\Requests;

use App\Enums\QuoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autenticação controlada por middleware
    }

    public function rules(): array
    {
        return [
            'client_id'          => ['required', 'exists:clients,id'],
            'client_address_id'  => ['required', 'exists:client_addresses,id'],
            'equipment_id'       => ['nullable', 'exists:client_equipments,id'],
            'status'             => ['nullable', new Enum(QuoteStatus::class)],
            'valid_until'        => ['nullable', 'date'],
            'discount_amount'    => ['nullable', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string'],
            'internal_notes'     => ['nullable', 'string'],
            
            // Logística
            'carrier'            => ['nullable', 'string', 'max:255'],
            'freight_price'      => ['nullable', 'numeric', 'min:0'],
            'freight_type'       => ['nullable', 'integer'],
            'volume'             => ['nullable', 'numeric', 'min:0'],
            'weight_gross'       => ['nullable', 'numeric', 'min:0'],
            'weight_net'         => ['nullable', 'numeric', 'min:0'],
            'delivery_deadline'  => ['nullable', 'string', 'max:255'],
            'warranty'           => ['nullable', 'string', 'max:255'],
            'validity'           => ['nullable', 'string', 'max:255'],
            
            // Itens do Orçamento
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'required_without:items.*.service_id', 'exists:products,id'],
            'items.*.service_id' => ['nullable', 'required_without:items.*.product_id', 'exists:services,id'],
            'items.*.description'=> ['required', 'string', 'max:255'],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required'         => 'O cliente é obrigatório.',
            'client_id.exists'           => 'Cliente selecionado é inválido.',
            'client_address_id.required' => 'O endereço de atendimento é obrigatório.',
            'items.required'             => 'Adicione pelo menos um item ao orçamento.',
            'items.min'                  => 'Adicione pelo menos um item ao orçamento.',
            'items.*.description.required'=> 'A descrição é obrigatória em cada item.',
            'items.*.quantity.required'  => 'A quantidade é obrigatória.',
            'items.*.quantity.min'       => 'A quantidade deve ser maior que zero.',
            'items.*.unit_price.required'=> 'O preço unitário é obrigatório.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $cleanedItems = [];
        if ($this->has('items') && is_array($this->items)) {
            foreach ($this->items as $index => $item) {
                if (empty($item['product_id']) && empty($item['service_id'])) continue;
                
                $cleanedItems[$index] = [
                    'product_id'  => !empty($item['product_id']) ? $item['product_id'] : null,
                    'service_id'  => !empty($item['service_id']) ? $item['service_id'] : null,
                    'description' => $item['description'] ?? '',
                    'quantity'    => isset($item['quantity']) ? (float) str_replace(',', '.', $item['quantity']) : 1,
                    'unit_price'  => isset($item['unit_price']) ? (float) str_replace(',', '.', str_replace('.', '', $item['unit_price'])) : 0,
                ];
            }
        }

        $this->merge([
            'items'           => $cleanedItems,
            'discount_amount' => $this->discount_amount ? (float) str_replace(',', '.', str_replace('.', '', $this->discount_amount)) : 0,
            'freight_price'   => $this->freight_price ? (float) str_replace(',', '.', str_replace('.', '', $this->freight_price)) : 0,
            'volume'          => $this->volume ? (float) str_replace(',', '.', str_replace('.', '', $this->volume)) : null,
            'weight_gross'    => $this->weight_gross ? (float) str_replace(',', '.', str_replace('.', '', $this->weight_gross)) : null,
            'weight_net'      => $this->weight_net ? (float) str_replace(',', '.', str_replace('.', '', $this->weight_net)) : null,
        ]);
    }
}
