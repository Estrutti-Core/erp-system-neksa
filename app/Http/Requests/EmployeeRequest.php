<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employeeId = $this->route('funcionario');

        return [
            'name' => ['required', 'string', 'max:255'],
            'document' => [
                'nullable',
                'string',
                'max:14',
                Rule::unique('employees', 'document')->ignore($employeeId),
            ],
            'role' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'hire_date' => ['required', 'date'],
            'active' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome do funcionário é obrigatório.',
            'document.unique' => 'Este CPF já está cadastrado.',
            'role.required' => 'O cargo é obrigatório.',
            'hire_date.required' => 'A data de admissão é obrigatória.',
            'hire_date.date' => 'A data de admissão deve ser válida.',
            'email.email' => 'O e-mail deve ser válido.',
        ];
    }
}
