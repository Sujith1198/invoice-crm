<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = CompanySetting::first();

        if (!$settings) {
            $settings = CompanySetting::create([
                'company_name' => 'Orange Invoicing & CRM Ltd',
                'company_logo' => null,
                'company_address' => "102 Orange Business Tower, Tech Zone 4\nBengaluru, Karnataka - 560001, India",
                'phone_number' => '+91 98765 43210',
                'email' => 'billing@orangeinvoice.com',
                'gstin' => '29AAAAA0000A1Z5',
                'invoice_prefix' => '',
                'default_currency' => 'INR',
                'default_currency_symbol' => '₹',
                'default_payment_terms' => 'Payment due within 15 days of invoice date',
                'invoice_footer' => 'Thank you for choosing Orange Invoicing. We appreciate your prompt business.',
                'terms_and_conditions' => "1. Goods/Services once sold will not be returned.\n2. Interest @ 18% p.a. will be charged on overdue payments.\n3. Subject to Bengaluru Jurisdiction.",
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:150',
            'company_logo' => 'nullable|string',
            'company_address' => 'nullable|string',
            'phone_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'gstin' => 'nullable|string|max:50',
            'invoice_prefix' => 'nullable|string|max:20',
            'default_currency' => 'required|string|max:10',
            'default_currency_symbol' => 'required|string|max:10',
            'default_payment_terms' => 'nullable|string',
            'invoice_footer' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
        ]);

        $settings = CompanySetting::first();
        if (!$settings) {
            $settings = CompanySetting::create($data);
        } else {
            $settings->update($data);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Settings updated successfully',
            'data' => $settings,
        ]);
    }
}

