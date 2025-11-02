<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigurationRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'value' => 'required|string',
      'version' => 'required|integer|min:1',
      'updated_by' => 'nullable|string|max:255',
    ];
  }

  public function messages(): array
  {
    return [
      'value.required' => 'O valor da configuração é obrigatório',
      'version.required' => 'A versão é obrigatória para Optimistic Locking',
      'version.integer' => 'A versão deve ser um número inteiro',
      'version.min' => 'A versão deve ser no mínimo 1',
    ];
  }
}
