<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IdempotencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'A descrição é obrigatória',
            'description.max' => 'A descrição não pode ter mais de 500 caracteres',
            'amount.required' => 'O valor é obrigatório',
            'amount.numeric' => 'O valor deve ser numérico',
            'amount.min' => 'O valor deve ser maior ou igual a zero',
        ];
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->header('Idempotency-Key');
    }
}
