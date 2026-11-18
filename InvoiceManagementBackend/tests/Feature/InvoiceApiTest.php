<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPhone;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    public function test_user_can_login_and_receive_token(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@orangeinvoice.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'token', 'user']);
    }

    public function test_dashboard_returns_real_calculations(): void
    {
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'statistics' => [
                        'total_customers',
                        'today_invoices',
                        'total_revenue',
                        'monthly_revenue',
                    ],
                    'charts',
                    'recent_invoices',
                    'recent_customers',
                ],
            ]);
    }

    public function test_customer_creation_with_multiple_phones(): void
    {
        $payload = [
            'first_name' => 'Kavita',
            'last_name' => 'Krishnan',
            'company_name' => 'Krishnan Electronics Ltd',
            'email' => 'kavita@krishnanelec.com',
            'address_line1' => 'Plot 88, Electronic City Phase 1',
            'city' => 'Bengaluru',
            'country' => 'India',
            'payment_method' => 'Monthly Pay',
            'phones' => [
                ['phone_number' => '+91 99887 76655', 'is_primary' => true, 'type' => 'Mobile'],
                ['phone_number' => '+91 80 2852 1122', 'is_primary' => false, 'type' => 'Work'],
            ],
        ];

        $response = $this->postJson('/api/customers', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.first_name', 'Kavita');

        $this->assertDatabaseHas('customers', ['email' => 'kavita@krishnanelec.com']);
        $this->assertDatabaseHas('customer_phones', ['phone_number' => '+91 99887 76655', 'is_primary' => 1]);
    }

    public function test_invoice_creation_calculates_totals_and_gst_accurately(): void
    {
        $customer = Customer::first();
        $item = Item::first();

        $payload = [
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+15 days')),
            'customer_id' => $customer->id,
            'customer_name' => $customer->full_name,
            'company_name' => $customer->company_name,
            'customer_address' => $customer->address_line1,
            'customer_phone' => $customer->primary_phone,
            'customer_email' => $customer->email,
            'payment_method' => 'Monthly Pay',
            'discount' => 100.00,
            'additional_charges' => 50.00,
            'status' => 'Generated',
            'items' => [
                [
                    'item_id' => $item->id,
                    'item_name' => 'Test Hardware Component',
                    'description' => 'Tested precision component',
                    'price' => 1000.00,
                    'quantity' => 2.00, // Line total = 2000
                    'gst_percentage' => 18.00, // GST = 360
                ],
            ],
        ];

        $response = $this->postJson('/api/invoices', $payload);

        $response->assertStatus(201);

        $created = $response->json('data');
        // Subtotal: 2000.00
        // Total GST: 360.00
        // Discount: 100.00
        // Charges: 50.00
        // Grand Total: 2000 + 360 - 100 + 50 = 2310.00
        $this->assertEquals(2000.00, (float) $created['subtotal']);
        $this->assertEquals(360.00, (float) $created['total_gst']);
        $this->assertEquals(2310.00, (float) $created['grand_total']);
        $this->assertEquals(2310.00, (float) $created['balance_due']);
        $this->assertEquals(0.00, (float) $created['amount_paid']);
    }

    public function test_recording_payment_updates_invoice_balance_and_status(): void
    {
        $invoice = Invoice::where('status', 'Generated')->first();
        $initialBalance = (float) $invoice->balance_due;

        $payAmount = 5000.00;
        $response = $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => $payAmount,
            'payment_date' => date('Y-m-d'),
            'payment_method' => 'Bank Transfer',
            'reference_number' => 'REF-TEST-12345',
            'notes' => 'Test payment receipt',
        ]);

        $response->assertStatus(200);

        $fresh = $invoice->fresh();
        $this->assertEquals($payAmount, (float) $fresh->amount_paid);
        $this->assertEquals(round($initialBalance - $payAmount, 2), (float) $fresh->balance_due);
        $this->assertEquals('Partially Paid', $fresh->status);
    }
}

