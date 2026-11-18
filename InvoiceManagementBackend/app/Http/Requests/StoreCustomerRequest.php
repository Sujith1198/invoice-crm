<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'company_name' => 'nullable|string|max:150',
            'email' => 'required|email|max:150|unique:customers,email',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'payment_method' => 'required|in:Daily Pay,Monthly Pay,15 Days Pay',
            'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|string',
            'phones' => 'required|array|min:1',
            'phones.*.phone_number' => 'required|string|min:7|max:20',
            'phones.*.is_primary' => 'nullable|boolean',
            'phones.*.type' => 'nullable|string|max:50',
        ];
    }
}

