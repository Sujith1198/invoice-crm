<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'required|string|max:150',
            'company_name' => 'nullable|string|max:150',
            'customer_address' => 'nullable|string',
            'customer_phone' => 'nullable|string|max:50',
            'customer_email' => 'nullable|email|max:150',
            'payment_method' => 'nullable|string|max:50',
            'discount' => 'nullable|numeric|min:0',
            'additional_charges' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'status' => 'nullable|in:Draft,Generated,Partially Paid,Paid,Overdue,Cancelled',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'nullable|exists:items,id',
            'items.*.item_name' => 'required|string|max:200',
            'items.*.description' => 'nullable|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.gst_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.sort_order' => 'nullable|integer',
        ];
    }
}

