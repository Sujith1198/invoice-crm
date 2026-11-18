<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'item_name' => 'required|string|max:200',
            'import_declaration' => 'nullable|string',
            'export_declaration' => 'nullable|string',
            'item_cost' => 'required|numeric|min:0',
            'gst_percentage' => 'required|numeric|min:0|max:100',
            'certificate_of_origin' => 'nullable|string|max:100',
            'xo_permit' => 'nullable|string|max:100',
            'amendment' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}

