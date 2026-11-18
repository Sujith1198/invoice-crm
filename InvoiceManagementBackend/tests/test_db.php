<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Users: " . App\Models\User::count() . PHP_EOL;
echo "Customers: " . App\Models\Customer::count() . PHP_EOL;
echo "Customer Phones: " . App\Models\CustomerPhone::count() . PHP_EOL;
echo "Items: " . App\Models\Item::count() . PHP_EOL;
echo "Invoices: " . App\Models\Invoice::count() . PHP_EOL;
echo "Invoice Items: " . App\Models\InvoiceItem::count() . PHP_EOL;
echo "Payments: " . App\Models\Payment::count() . PHP_EOL;
echo "Total Payments Recorded: ₹" . App\Models\Payment::sum('amount') . PHP_EOL;
echo "Total Invoices Grand Total: ₹" . App\Models\Invoice::sum('grand_total') . PHP_EOL;
echo "Total Invoices Balance Due: ₹" . App\Models\Invoice::sum('balance_due') . PHP_EOL;

