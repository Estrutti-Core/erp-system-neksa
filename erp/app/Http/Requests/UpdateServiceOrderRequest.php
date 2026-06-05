<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('serviceOrder'));
    }

    public function rules(): array
    {
        return [
            'client_id'           => ['sometimes', 'exists:clients,id'],
            'client_address_id'   => ['nullable', 'exists:client_addresses,id'],
            'equipment_id'        => [
                'nullable',
                Rule::exists('client_equipments', 'id')->where(function ($query) {
                    $clientId = $this->client_id ?? $this->route('serviceOrder')->client_id;
                    $query->where('client_id', $clientId);
                })
            ],
            'technician_id'       => ['nullable', 'exists:users,id'],
            'priority'            => ['sometimes', Rule::enum(\App\Enums\ServiceOrderPriority::class)],
            'description'         => ['sometimes', 'string', 'min:10'],
            'services_performed'  => ['nullable', 'string'],
            'internal_notes'      => ['nullable', 'string'],
            'service_amount'      => ['nullable', 'numeric', 'min:0'],
            'parts_amount'        => ['nullable', 'numeric', 'min:0'],
            'total_amount'        => ['nullable', 'numeric', 'min:0'],
            'scheduled_at'        => ['nullable', 'date'],
        ];
    }
}
