<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_email' => ['required', 'email', 'max:255'],
            'total' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'poison' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_email' => 'customer email',
            'total' => 'total',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'poison' => $this->boolean('poison'),
        ]);
    }
}
