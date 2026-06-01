<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StoreShopifyCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:64', 'required_without:email'],
            'province' => ['nullable', 'string', 'max:128'],
            'zip' => ['nullable', 'string', 'max:32'],
            'tags' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
