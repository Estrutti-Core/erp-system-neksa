<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controlado pelo authorizeResource do Controller
    }

    public function rules(): array
    {
        return [
            'name'                   => ['required', 'string', 'max:255'],
            'document'               => ['nullable', 'string', 'max:20'],
            'document_type'          => ['nullable', 'in:cpf,cnpj'],
            'phone'                  => ['nullable', 'string', 'max:20'],
            'phone_secondary'        => ['nullable', 'string', 'max:20'],
            'email'                  => ['nullable', 'email', 'max:255'],
            'notes'                  => ['nullable', 'string'],

            // Dados PJ adicionais
            'social_name'            => ['nullable', 'string', 'max:255'],
            'trade_name'             => ['nullable', 'string', 'max:255'],
            'sector'                 => ['nullable', 'string', 'max:255'],
            'opening_date'           => ['nullable', 'date'],
            'capital_social'         => ['nullable', 'numeric', 'min:0'],
            'company_size'           => ['nullable', 'string', 'max:255'],
            'legal_nature'           => ['nullable', 'string', 'max:255'],
            'registration_status'    => ['nullable', 'string', 'max:255'],

            // CNAE
            'main_cnae_code'         => ['nullable', 'string', 'max:20'],
            'main_cnae_description'  => ['nullable', 'string'],
            'secondary_cnaes'        => ['nullable', 'array'],
            'secondary_cnaes.*.code' => ['required_with:secondary_cnaes', 'string', 'max:20'],
            'secondary_cnaes.*.description' => ['required_with:secondary_cnaes', 'string'],

            // Contatos
            'contacts'               => ['nullable', 'array'],
            'contacts.*.id'          => ['nullable', 'integer'],
            'contacts.*.name'        => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.email'       => ['nullable', 'email', 'max:255'],
            'contacts.*.phone'       => ['nullable', 'string', 'max:20'],
            'contacts.*.whatsapp'    => ['nullable', 'string', 'max:20'],
            'contacts.*.role'        => ['nullable', 'string', 'max:100'],
            'contacts.*.is_primary'  => ['nullable', 'boolean'],
            'contacts.*.is_phone_blocked' => ['nullable', 'boolean'],
            'contacts.*.is_whatsapp_blocked' => ['nullable', 'boolean'],
            'contacts.*.is_email_blocked' => ['nullable', 'boolean'],

            // Equipamentos
            'equipments'                  => ['nullable', 'array'],
            'equipments.*.id'             => ['nullable', 'integer'],
            'equipments.*.name'           => ['required_with:equipments', 'string', 'max:255'],
            'equipments.*.brand'          => ['nullable', 'string', 'max:255'],
            'equipments.*.model'          => ['nullable', 'string', 'max:255'],
            'equipments.*.serial_number'  => ['nullable', 'string', 'max:255'],
            'equipments.*.notes'          => ['nullable', 'string'],

            // Endereço principal
            'zip_code'               => ['nullable', 'string', 'max:10'],
            'street'                 => ['required', 'string', 'max:255'],
            'number'                 => ['nullable', 'string', 'max:20'],
            'complement'             => ['nullable', 'string', 'max:100'],
            'neighborhood'           => ['nullable', 'string', 'max:100'],
            'city'                   => ['required', 'string', 'max:100'],
            'state'                  => ['required', 'string', 'size:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'O nome do cliente é obrigatório.',
            'street.required' => 'O logradouro do endereço é obrigatório.',
            'city.required'   => 'A cidade é obrigatória.',
            'state.required'  => 'O estado é obrigatório.',
            'state.size'      => 'O estado deve ter 2 caracteres (ex: SP).',
            'contacts.*.name.required_with' => 'O nome do contato é obrigatório.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Limpar máscaras de CPF/CNPJ e CEP
        $this->merge([
            'document' => $this->document ? preg_replace('/\D/', '', $this->document) : null,
            'zip_code' => $this->zip_code ? preg_replace('/\D/', '', $this->zip_code) : null,
            'capital_social' => $this->capital_social ? (float) str_replace(',', '.', str_replace('.', '', $this->capital_social)) : null,
        ]);
    }
}
