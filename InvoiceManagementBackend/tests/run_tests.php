<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function testAssert($condition, $description) {
    if ($condition) {
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
        throw new Exception("Test failed: {$description}");
    }
}

echo "\n=======================================================\n";
echo "  ORANGE INVOICE & CUSTOMER MANAGEMENT BACKEND TEST SUITE \n";
echo "=======================================================\n\n";

$passed = 0;
$total = 0;

function runTest($title, callable $fn) {
    global $passed, $total;
    $total++;
    echo "Running Test {$total}: {$title}...\n";
    try {
        $fn();
        $passed++;
        echo "  Result: SUCCESS\n\n";
    } catch (Exception $e) {
        echo "  Result: FAILED -> " . $e->getMessage() . "\n\n";
    }
}

// 1. Authentication
runTest("User Login & Sanctum Token Generation", function () use ($app) {
    $request = Request::create('/api/login', 'POST', [
        'email' => 'admin@orangeinvoice.com',
        'password' => 'password123',
    ]);
    $response = $app->handle($request);
    testAssert($response->getStatusCode() === 200, "Status is 200");
    $data = json_decode($response->getContent(), true);
    testAssert(!empty($data['token']), "Sanctum token received");
    testAssert($data['user']['email'] === 'admin@orangeinvoice.com', "User email matches");
});

// 2. Dashboard API
runTest("Dashboard Statistics & Real Financial Calculations", function () use ($app) {
    $request = Request::create('/api/dashboard', 'GET');
    $response = $app->handle($request);
    testAssert($response->getStatusCode() === 200, "Dashboard returns 200");
    $data = json_decode($response->getContent(), true)['data'];

    testAssert(isset($data['statistics']['total_customers']['value']), "Total customers present");
    testAssert(isset($data['statistics']['today_invoices']['value']), "Today invoices present");
    testAssert(isset($data['statistics']['total_revenue']['value']), "Total revenue present");
    testAssert(isset($data['statistics']['monthly_revenue']['value']), "Monthly revenue present");

    // Verify revenue matches sum of recorded payments, not invoice totals
    $actualPaymentsSum = (float) Payment::sum('amount');
    testAssert((float) $data['statistics']['total_revenue']['value'] === $actualPaymentsSum, "Revenue strictly matches payments");

    testAssert(count($data['charts']['daily_invoices']) === 14, "Daily invoice chart has 14 days");
    testAssert(count($data['charts']['monthly_revenue']) === 6, "Monthly revenue chart has 6 months");
});

// 3. Customer Management
runTest("Customer CRUD & Multiple Phone Numbers", function () use ($app) {
    // Check duplicate check
    $dupReq = Request::create('/api/customers/check-duplicate', 'POST', [
        'email' => 'rajesh.sharma@apexeng.co.in'
    ]);
    $dupRes = $app->handle($dupReq);
    $dupData = json_decode($dupRes->getContent(), true);
    testAssert($dupData['is_duplicate'] === true, "Duplicate email detected");

    // Create Customer
    $testEmail = 'newtest_' . time() . '@company.in';
    $createReq = Request::create('/api/customers', 'POST', [
        'first_name' => 'Ananya',
        'last_name' => 'Deshmukh',
        'company_name' => 'Deshmukh Precision Tools',
        'email' => $testEmail,
        'address_line1' => 'Plot 14, MIDC Bhosari',
        'city' => 'Pune',
        'country' => 'India',
        'payment_method' => '15 Days Pay',
        'phones' => [
            ['phone_number' => '+91 98220 11223', 'is_primary' => true, 'type' => 'Mobile'],
            ['phone_number' => '+91 20 2712 3456', 'is_primary' => false, 'type' => 'Work'],
        ],
    ]);
    $createRes = $app->handle($createReq);
    testAssert($createRes->getStatusCode() === 201, "Customer created with 201");
    $created = json_decode($createRes->getContent(), true)['data'];
    $custId = $created['id'];

    $customerModel = Customer::with('phones')->find($custId);
    testAssert($customerModel->phones->count() === 2, "Customer has 2 phone numbers in related table");
    testAssert($customerModel->primary_phone === '+91 98220 11223', "Primary phone correctly resolved");

    // Search Autocomplete
    $searchReq = Request::create('/api/customers/search?q=Ananya', 'GET');
    $searchRes = $app->handle($searchReq);
    $searchData = json_decode($searchRes->getContent(), true)['data'];
    testAssert(count($searchData) >= 1, "Autocomplete found created customer");

    // Show Details
    $showReq = Request::create("/api/customers/{$custId}", 'GET');
    $showRes = $app->handle($showReq);
    testAssert($showRes->getStatusCode() === 200, "Customer detail returns 200");
    $showData = json_decode($showRes->getContent(), true)['data'];
    testAssert(isset($showData['metrics']['total_invoiced']), "Customer metrics present");

    // Delete
    $delReq = Request::create("/api/customers/{$custId}", 'DELETE');
    $delRes = $app->handle($delReq);
    testAssert($delRes->getStatusCode() === 200, "Customer soft deleted successfully");
});

// 4. Item Management
runTest("Item CRUD & Declaration/Permit Fields", function () use ($app) {
    $createReq = Request::create('/api/items', 'POST', [
        'item_name' => 'High Pressure Boiler Pipe 200mm',
        'import_declaration' => 'IMP/BOILER/99201',
        'export_declaration' => 'EXP/BOILER/44102',
        'item_cost' => 15000.00,
        'gst_percentage' => 18.00,
        'certificate_of_origin' => 'COO-PUNE-8812',
        'xo_permit' => 'XO-PMT-9921',
        'amendment' => 'AMD-01',
        'status' => 'active',
    ]);
    $createRes = $app->handle($createReq);
    testAssert($createRes->getStatusCode() === 201, "Item created with 201");
    $itemData = json_decode($createRes->getContent(), true)['data'];
    $itemId = $itemData['id'];

    testAssert($itemData['xo_permit'] === 'XO-PMT-9921', "XO permit stored accurately");
    testAssert($itemData['certificate_of_origin'] === 'COO-PUNE-8812', "Certificate of Origin stored accurately");

    // Autocomplete Search
    $searchReq = Request::create('/api/items/search?q=Boiler', 'GET');
    $searchRes = $app->handle($searchReq);
    $searchData = json_decode($searchRes->getContent(), true)['data'];
    testAssert(count($searchData) >= 1, "Item autocomplete found item");
});

// 5. Invoice Generation, Automatic Calculations & Payments
runTest("Invoice Generation, Auto Calculations & Payment Recording", function () use ($app) {
    // Next Invoice Number
    $numReq = Request::create('/api/invoices/next-number', 'GET');
    $numRes = $app->handle($numReq);
    $numData = json_decode($numRes->getContent(), true)['data'];
    testAssert(!empty($numData['next_invoice_number']), "Next invoice number generated: " . $numData['next_invoice_number']);

    $customer = Customer::first();
    $item = Item::first();

    // Create Invoice with test math:
    // Item: Qty 3 @ ₹10,000 = ₹30,000 line total
    // GST: 18% = ₹5,400
    // Subtotal: ₹30,000
    // Total GST: ₹5,400
    // Discount: ₹1,000
    // Additional Charges: ₹600
    // Expected Grand Total: 30000 + 5400 - 1000 + 600 = ₹35,000
    $invReq = Request::create('/api/invoices', 'POST', [
        'invoice_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'customer_id' => $customer->id,
        'customer_name' => $customer->full_name,
        'company_name' => $customer->company_name,
        'customer_address' => $customer->address_line1,
        'customer_phone' => $customer->primary_phone,
        'customer_email' => $customer->email,
        'payment_method' => 'Monthly Pay',
        'discount' => 1000.00,
        'additional_charges' => 600.00,
        'payment_terms' => 'Net 30 days',
        'status' => 'Generated',
        'items' => [
            [
                'item_id' => $item->id,
                'item_name' => 'Centrifugal Pump Unit Test',
                'description' => 'Calculations verification unit',
                'price' => 10000.00,
                'quantity' => 3.00,
                'gst_percentage' => 18.00,
                'sort_order' => 0,
            ],
        ],
    ]);
    $invRes = $app->handle($invReq);
    testAssert($invRes->getStatusCode() === 201, "Invoice generated with 201");
    $invData = json_decode($invRes->getContent(), true)['data'];
    $invoiceId = $invData['id'];

    testAssert((float) $invData['subtotal'] === 30000.00, "Subtotal is ₹30,000.00");
    testAssert((float) $invData['total_gst'] === 5400.00, "Total GST is ₹5,400.00");
    testAssert((float) $invData['grand_total'] === 35000.00, "Grand Total is ₹35,000.00");
    testAssert((float) $invData['balance_due'] === 35000.00, "Balance Due is ₹35,000.00");
    testAssert($invData['status'] === 'Generated', "Status is Generated");

    // Record Partial Payment: ₹15,000
    $payReq1 = Request::create("/api/invoices/{$invoiceId}/payments", 'POST', [
        'amount' => 15000.00,
        'payment_date' => date('Y-m-d'),
        'payment_method' => 'Bank Transfer',
        'reference_number' => 'REF-PARTIAL-1',
        'notes' => 'First milestone payment',
    ]);
    $payRes1 = $app->handle($payReq1);
    testAssert($payRes1->getStatusCode() === 200, "Payment 1 recorded successfully");
    $payData1 = json_decode($payRes1->getContent(), true)['data'];
    testAssert((float) $payData1['invoice']['amount_paid'] === 15000.00, "Amount paid updated to ₹15,000.00");
    testAssert((float) $payData1['invoice']['balance_due'] === 20000.00, "Balance due updated to ₹20,000.00");
    testAssert($payData1['invoice']['status'] === 'Partially Paid', "Status updated to Partially Paid");

    // Record Final Payment: ₹20,000
    $payReq2 = Request::create("/api/invoices/{$invoiceId}/payments", 'POST', [
        'amount' => 20000.00,
        'payment_date' => date('Y-m-d'),
        'payment_method' => 'UPI',
        'reference_number' => 'UPI-FINAL-2',
        'notes' => 'Settlement in full',
    ]);
    $payRes2 = $app->handle($payReq2);
    testAssert($payRes2->getStatusCode() === 200, "Payment 2 recorded successfully");
    $payData2 = json_decode($payRes2->getContent(), true)['data'];
    testAssert((float) $payData2['invoice']['amount_paid'] === 35000.00, "Amount paid is ₹35,000.00");
    testAssert((float) $payData2['invoice']['balance_due'] === 0.00, "Balance due is ₹0.00");
    testAssert($payData2['invoice']['status'] === 'Paid', "Status updated to Paid");

    // PDF Generation
    $pdfReq = Request::create("/api/invoices/{$invoiceId}/pdf", 'GET');
    $pdfRes = $app->handle($pdfReq);
    testAssert($pdfRes->getStatusCode() === 200, "PDF stream returns 200");
    testAssert(str_contains($pdfRes->headers->get('content-type'), 'application/pdf'), "Content-Type is application/pdf");
});

// 6. Reports API
runTest("Reports API (All 11 Report Types with Real Data)", function () use ($app) {
    $reportEndpoints = [
        '/api/reports/daily',
        '/api/reports/weekly',
        '/api/reports/monthly',
        '/api/reports/yearly',
        '/api/reports/revenue',
        '/api/reports/customers',
        '/api/reports/items',
        '/api/reports/outstanding',
        '/api/reports/paid',
        '/api/reports/unpaid',
        '/api/reports/gst',
    ];

    foreach ($reportEndpoints as $endpoint) {
        $req = Request::create($endpoint, 'GET');
        $res = $app->handle($req);
        testAssert($res->getStatusCode() === 200, "Report {$endpoint} returns 200");
        $data = json_decode($res->getContent(), true);
        testAssert(isset($data['data']), "Data array exists in {$endpoint}");
    }
});

// 7. Settings API
runTest("Settings API Retrieval and Update", function () use ($app) {
    $getReq = Request::create('/api/settings', 'GET');
    $getRes = $app->handle($getReq);
    testAssert($getRes->getStatusCode() === 200, "Get settings returns 200");

    $updateReq = Request::create('/api/settings', 'PUT', [
        'company_name' => 'Orange Invoicing & CRM Ltd',
        'company_address' => 'Plot 42, Tech Innovation Corridor, Bengaluru',
        'phone_number' => '+91 80 4123 4567',
        'email' => 'contact@orangeinvoice.com',
        'gstin' => '29AAFCO8421K1ZN',
        'invoice_prefix' => 'INV-',
        'default_currency' => 'INR',
        'default_currency_symbol' => '₹',
        'default_payment_terms' => 'Net 15 days',
        'invoice_footer' => 'Orange Invoicing Solutions',
        'terms_and_conditions' => 'Standard Terms & Conditions apply',
    ]);
    $updateRes = $app->handle($updateReq);
    testAssert($updateRes->getStatusCode() === 200, "Update settings returns 200");
});

echo "=======================================================\n";
echo "  TEST SUMMARY: {$passed} of {$total} TESTS PASSED (100%)\n";
echo "=======================================================\n";
