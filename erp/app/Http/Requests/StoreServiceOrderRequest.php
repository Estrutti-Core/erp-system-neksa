<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\ServiceOrder::class);
    }

    public function rules(): array
    {
        return [
            'client_id'          => ['required', 'exists:clients,id'],
            'client_address_id'  => ['nullable', 'exists:client_addresses,id'],
            'equipment_id'       => [
                'nullable',
                Rule::exists('client_equipments', 'id')->where(function ($query) {
                    $query->where('client_id', $this->client_id);
                })
            ],
            'technician_id'      => ['nullable', 'exists:users,id'],
            'priority'           => ['required', Rule::enum(\App\Enums\ServiceOrderPriority::class)],
            'description'        => ['required', 'string', 'min:10'],
            'scheduled_at'       => ['nullable', 'date'],
            'internal_notes'     => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required'   => 'Selecione um cliente.',
            'client_id.exists'     => 'Cliente não encontrado.',
            'description.required' => 'A descrição do problema é obrigatória.',
            'description.min'      => 'A descrição deve ter pelo menos 10 caracteres.',
            'priority.required'    => 'Selecione a prioridade da OS.',
        ];
    }
}
