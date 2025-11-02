<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'name' => 'required|string|max:255',
      'description' => 'nullable|string',
      'price' => 'required|numeric|min:0',
      'stock' => 'required|integer|min:0',
      'active' => 'boolean',
    ];
  }

  public function messages(): array
  {
    return [
      'name.required' => 'O nome do produto é obrigatório',
      'price.required' => 'O preço do produto é obrigatório',
      'price.min' => 'O preço deve ser maior ou igual a zero',
      'stock.required' => 'O estoque do produto é obrigatório',
      'stock.min' => 'O estoque deve ser maior ou igual a zero',
    ];
  }
}
