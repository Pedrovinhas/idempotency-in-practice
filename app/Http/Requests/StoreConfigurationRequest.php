<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConfigurationRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'key' => 'required|string|max:255|unique:configurations,key',
      'value' => 'required|string',
      'updated_by' => 'nullable|string|max:255',
    ];
  }

  public function messages(): array
  {
    return [
      'key.required' => 'A chave da configuração é obrigatória',
      'key.unique' => 'Já existe uma configuração com essa chave',
      'value.required' => 'O valor da configuração é obrigatório',
    ];
  }
}
