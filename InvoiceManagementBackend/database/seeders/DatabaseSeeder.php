<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerPhone;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSequence;
use App\Models\Item;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Default Admin User
        $user = User::updateOrCreate(
            ['email' => 'admin@orangeinvoice.com'],
            [
                'name' => 'Orange Admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Default Company Settings
        CompanySetting::updateOrCreate(
            ['id' => 1],
            [
                'company_name' => 'Orange Invoicing & Logistics Ltd',
                'company_logo' => null,
                'company_address' => "Plot 42, Tech Innovation Corridor, Phase 1\nBengaluru, Karnataka - 560100, India",
                'phone_number' => '+91 80 4123 4567',
                'email' => 'contact@orangeinvoice.com',
                'gstin' => '29AAFCO8421K1ZN',
                'invoice_prefix' => 'INV-',
                'default_currency' => 'INR',
                'default_currency_symbol' => '₹',
                'default_payment_terms' => 'Net 15 days from invoice issuance date',
                'invoice_footer' => 'Thank you for your valuable partnership. For assistance, reach us at contact@orangeinvoice.com',
                'terms_and_conditions' => "1. Goods and services supplied are subject to Orange Invoicing standard trade terms.\n2. Overdue payments accrue interest at 1.5% per month.\n3. All disputes are subject to the exclusive jurisdiction of the courts of Bengaluru.",
            ]
        );

        // 3. Seed Items
        $itemsData = [
            [
                'item_name' => 'Industrial Centrifugal Pump 50HP',
                'import_declaration' => 'IMP/2026/BLR/88219 - Customs cleared through Bangalore ICD',
                'export_declaration' => 'EXP/DE/441092 - Port of Hamburg outbound',
                'item_cost' => 45000.00,
                'gst_percentage' => 18.00,
                'certificate_of_origin' => 'COO-IND-2026-9912',
                'xo_permit' => 'XO-PMT-7821-B',
                'amendment' => 'AMD-02/RevA',
                'status' => 'active',
            ],
            [
                'item_name' => 'Heavy Duty Hydraulic Valve Assembly',
                'import_declaration' => 'IMP/2026/MAA/10492 - Port of Chennai Customs',
                'export_declaration' => 'EXP/SG/209182 - Singapore Jurong Port Transit',
                'item_cost' => 12500.00,
                'gst_percentage' => 18.00,
                'certificate_of_origin' => 'COO-IND-2026-5510',
                'xo_permit' => 'XO-PMT-4019-A',
                'amendment' => 'AMD-01',
                'status' => 'active',
            ],
            [
                'item_name' => 'Automated PLC Control Panel Unit',
                'import_declaration' => 'IMP/2026/BOM/33019 - Nhava Sheva JNPT Customs',
                'export_declaration' => 'EXP/UAE/990123 - Jebel Ali Port destination',
                'item_cost' => 85000.00,
                'gst_percentage' => 18.00,
                'certificate_of_origin' => 'COO-IND-2026-1188',
                'xo_permit' => 'XO-PMT-9182-C',
                'amendment' => 'AMD-03/FullSpec',
                'status' => 'active',
            ],
            [
                'item_name' => 'Precision Stainless Steel Flanges DN100',
                'import_declaration' => 'IMP/2026/DEL/77120 - Tughlakabad ICD clearance',
                'export_declaration' => 'EXP/US/102938 - Long Beach Marine clearance',
                'item_cost' => 3800.00,
                'gst_percentage' => 12.00,
                'certificate_of_origin' => 'COO-IND-2026-3390',
                'xo_permit' => 'XO-PMT-2201-D',
                'amendment' => 'None',
                'status' => 'active',
            ],
            [
                'item_name' => 'High-Speed Conveyor Belt Roller 1200mm',
                'import_declaration' => 'IMP/2026/HYD/40192 - Hyderabad Cargo Airport',
                'export_declaration' => 'EXP/UK/551209 - Felixstowe Container terminal',
                'item_cost' => 9200.00,
                'gst_percentage' => 18.00,
                'certificate_of_origin' => 'COO-IND-2026-7721',
                'xo_permit' => 'XO-PMT-6610-A',
                'amendment' => 'AMD-01/RubberSpec',
                'status' => 'active',
            ],
            [
                'item_name' => 'Customs Clearance & Documentation Service',
                'import_declaration' => 'CHA Clearance License Ref # 4492/KA',
                'export_declaration' => 'EDI Filing Acknowledgement # 992019',
                'item_cost' => 7500.00,
                'gst_percentage' => 18.00,
                'certificate_of_origin' => 'CHAMBER-COMM-BLR',
                'xo_permit' => 'XO-SRV-001',
                'amendment' => 'Standard',
                'status' => 'active',
            ],
        ];

        $createdItems = [];
        foreach ($itemsData as $it) {
            $createdItems[] = Item::create($it);
        }

        // 4. Seed Customers with multiple phones
        $customersData = [
            [
                'first_name' => 'Rajesh',
                'last_name' => 'Sharma',
                'company_name' => 'Apex Engineering Works Pvt Ltd',
                'email' => 'rajesh.sharma@apexeng.co.in',
                'address_line1' => '24 Industrial Suburb, Peenya 2nd Stage',
                'address_line2' => 'Near Central Warehouse',
                'city' => 'Bengaluru',
                'country' => 'India',
                'payment_method' => 'Monthly Pay',
                'status' => 'active',
                'phones' => [
                    ['phone_number' => '+91 98450 12345', 'is_primary' => true, 'type' => 'Mobile'],
                    ['phone_number' => '+91 80 2839 0011', 'is_primary' => false, 'type' => 'Work'],
                ],
            ],
            [
                'first_name' => 'Priya',
                'last_name' => 'Nair',
                'company_name' => 'Oceanic Marine & Logistics Ltd',
                'email' => 'priya.nair@oceanicmarine.com',
                'address_line1' => 'Harbor View Commercial Complex, Suite 501',
                'address_line2' => 'Willingdon Island',
                'city' => 'Kochi',
                'country' => 'India',
                'payment_method' => '15 Days Pay',
                'status' => 'active',
                'phones' => [
                    ['phone_number' => '+91 97451 98765', 'is_primary' => true, 'type' => 'Mobile'],
                    ['phone_number' => '+91 484 266 7788', 'is_primary' => false, 'type' => 'Work'],
                    ['phone_number' => '+91 94470 55443', 'is_primary' => false, 'type' => 'Alternate'],
                ],
            ],
            [
                'first_name' => 'Vikram',
                'last_name' => 'Mehta',
                'company_name' => 'Gujarat Precision Forgings Corp',
                'email' => 'vmehta@gujaratforgings.in',
                'address_line1' => 'GIDC Estate, Phase 3, Plot 110',
                'address_line2' => 'Makarpura',
                'city' => 'Vadodara',
                'country' => 'India',
                'payment_method' => 'Daily Pay',
                'status' => 'active',
                'phones' => [
                    ['phone_number' => '+91 98250 33445', 'is_primary' => true, 'type' => 'Mobile'],
                    ['phone_number' => '+91 265 264 9900', 'is_primary' => false, 'type' => 'Work'],
                ],
            ],
            [
                'first_name' => 'Sunita',
                'last_name' => 'Patel',
                'company_name' => 'Zenith Global Trading FZE',
                'email' => 'sunita.patel@zenithglobal.com',
                'address_line1' => 'Tower B, 14th Floor, World Trade Centre',
                'address_line2' => 'Cuffe Parade',
                'city' => 'Mumbai',
                'country' => 'India',
                'payment_method' => 'Monthly Pay',
                'status' => 'active',
                'phones' => [
                    ['phone_number' => '+91 98201 88990', 'is_primary' => true, 'type' => 'Mobile'],
                    ['phone_number' => '+91 22 6655 4400', 'is_primary' => false, 'type' => 'Work'],
                ],
            ],
            [
                'first_name' => 'Amitabh',
                'last_name' => 'Verma',
                'company_name' => 'Verma Hydrotech Systems',
                'email' => 'averma@hydrotechsystems.net',
                'address_line1' => 'Sector 58, Industrial Model Township',
                'address_line2' => 'Ballabgarh Road',
                'city' => 'Faridabad',
                'country' => 'India',
                'payment_method' => '15 Days Pay',
                'status' => 'active',
                'phones' => [
                    ['phone_number' => '+91 98110 44556', 'is_primary' => true, 'type' => 'Mobile'],
                ],
            ],
        ];

        $createdCustomers = [];
        foreach ($customersData as $cData) {
            $phones = $cData['phones'];
            unset($cData['phones']);

            $cust = Customer::create($cData);
            foreach ($phones as $p) {
                CustomerPhone::create([
                    'customer_id' => $cust->id,
                    'phone_number' => $p['phone_number'],
                    'is_primary' => $p['is_primary'],
                    'type' => $p['type'],
                ]);
            }
            $createdCustomers[] = $cust;
        }

        // 5. Seed Invoices & Payments (spanning current and previous periods)
        $year = (int) date('Y');
        InvoiceSequence::updateOrCreate(
            ['year' => $year, 'prefix' => 'INV'],
            ['last_number' => 4]
        );

        // Invoice 1: Paid in full (Monthly Pay, Rajesh Sharma)
        $inv1 = Invoice::create([
            'invoice_number' => "INV-{$year}-0001",
            'invoice_date' => Carbon::today()->subDays(10)->toDateString(),
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
            'customer_id' => $createdCustomers[0]->id,
            'customer_name' => $createdCustomers[0]->full_name,
            'company_name' => $createdCustomers[0]->company_name,
            'customer_address' => $createdCustomers[0]->address_line1 . ', ' . $createdCustomers[0]->city,
            'customer_phone' => $createdCustomers[0]->primary_phone,
            'customer_email' => $createdCustomers[0]->email,
            'payment_method' => 'Monthly Pay',
            'subtotal' => 90000.00,
            'total_gst' => 16200.00,
            'discount' => 2000.00,
            'additional_charges' => 500.00,
            'grand_total' => 104700.00,
            'amount_paid' => 104700.00,
            'balance_due' => 0.00,
            'payment_terms' => 'Net 15 days',
            'notes' => 'Annual equipment supply agreement order # 104.',
            'terms_and_conditions' => 'Standard warranty applicable for 12 months.',
            'status' => 'Paid',
            'created_at' => Carbon::today()->subDays(10),
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv1->id,
            'item_id' => $createdItems[0]->id,
            'item_name' => $createdItems[0]->item_name,
            'description' => 'Industrial Centrifugal Pump 50HP with custom base mountings',
            'price' => 45000.00,
            'quantity' => 2.00,
            'gst_percentage' => 18.00,
            'gst_amount' => 16200.00,
            'line_total' => 90000.00,
            'sort_order' => 0,
        ]);

        Payment::create([
            'invoice_id' => $inv1->id,
            'payment_number' => "PAY-{$year}-0001",
            'payment_date' => Carbon::today()->subDays(8)->toDateString(),
            'amount' => 104700.00,
            'payment_method' => 'Bank Transfer',
            'reference_number' => 'NEFT-AXIS-99201948',
            'notes' => 'Full payment received through Axis Bank',
        ]);

        // Invoice 2: Partially Paid (Sunita Patel)
        $inv2 = Invoice::create([
            'invoice_number' => "INV-{$year}-0002",
            'invoice_date' => Carbon::today()->subDays(4)->toDateString(),
            'due_date' => Carbon::today()->addDays(11)->toDateString(),
            'customer_id' => $createdCustomers[3]->id,
            'customer_name' => $createdCustomers[3]->full_name,
            'company_name' => $createdCustomers[3]->company_name,
            'customer_address' => $createdCustomers[3]->address_line1 . ', ' . $createdCustomers[3]->city,
            'customer_phone' => $createdCustomers[3]->primary_phone,
            'customer_email' => $createdCustomers[3]->email,
            'payment_method' => 'Monthly Pay',
            'subtotal' => 85000.00,
            'total_gst' => 15300.00,
            'discount' => 0.00,
            'additional_charges' => 1000.00,
            'grand_total' => 101300.00,
            'amount_paid' => 50000.00,
            'balance_due' => 51300.00,
            'payment_terms' => '50% advance, balance on delivery',
            'notes' => 'Export packing and documentation completed.',
            'terms_and_conditions' => 'Standard export terms applicable.',
            'status' => 'Partially Paid',
            'created_at' => Carbon::today()->subDays(4),
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv2->id,
            'item_id' => $createdItems[2]->id,
            'item_name' => $createdItems[2]->item_name,
            'description' => 'Automated PLC Control Panel Unit with SCADA interface',
            'price' => 85000.00,
            'quantity' => 1.00,
            'gst_percentage' => 18.00,
            'gst_amount' => 15300.00,
            'line_total' => 85000.00,
            'sort_order' => 0,
        ]);

        Payment::create([
            'invoice_id' => $inv2->id,
            'payment_number' => "PAY-{$year}-0002",
            'payment_date' => Carbon::today()->subDays(3)->toDateString(),
            'amount' => 50000.00,
            'payment_method' => 'UPI',
            'reference_number' => 'UPI/401929381920',
            'notes' => '50% advance received via corporate UPI',
        ]);

        // Invoice 3: Generated Today (Priya Nair)
        $inv3 = Invoice::create([
            'invoice_number' => "INV-{$year}-0003",
            'invoice_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(15)->toDateString(),
            'customer_id' => $createdCustomers[1]->id,
            'customer_name' => $createdCustomers[1]->full_name,
            'company_name' => $createdCustomers[1]->company_name,
            'customer_address' => $createdCustomers[1]->address_line1 . ', ' . $createdCustomers[1]->city,
            'customer_phone' => $createdCustomers[1]->primary_phone,
            'customer_email' => $createdCustomers[1]->email,
            'payment_method' => '15 Days Pay',
            'subtotal' => 25000.00,
            'total_gst' => 4500.00,
            'discount' => 500.00,
            'additional_charges' => 0.00,
            'grand_total' => 29000.00,
            'amount_paid' => 0.00,
            'balance_due' => 29000.00,
            'payment_terms' => 'Net 15 days',
            'notes' => 'Routine shipment delivery to Kochi port terminal.',
            'terms_and_conditions' => 'Standard commercial terms.',
            'status' => 'Generated',
            'created_at' => Carbon::today(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv3->id,
            'item_id' => $createdItems[1]->id,
            'item_name' => $createdItems[1]->item_name,
            'description' => 'Heavy Duty Hydraulic Valve Assembly (Marine grade)',
            'price' => 12500.00,
            'quantity' => 2.00,
            'gst_percentage' => 18.00,
            'gst_amount' => 4500.00,
            'line_total' => 25000.00,
            'sort_order' => 0,
        ]);

        // Invoice 4: Overdue (Vikram Mehta)
        $inv4 = Invoice::create([
            'invoice_number' => "INV-{$year}-0004",
            'invoice_date' => Carbon::today()->subDays(25)->toDateString(),
            'due_date' => Carbon::today()->subDays(5)->toDateString(),
            'customer_id' => $createdCustomers[2]->id,
            'customer_name' => $createdCustomers[2]->full_name,
            'company_name' => $createdCustomers[2]->company_name,
            'customer_address' => $createdCustomers[2]->address_line1 . ', ' . $createdCustomers[2]->city,
            'customer_phone' => $createdCustomers[2]->primary_phone,
            'customer_email' => $createdCustomers[2]->email,
            'payment_method' => 'Daily Pay',
            'subtotal' => 15200.00,
            'total_gst' => 1824.00,
            'discount' => 0.00,
            'additional_charges' => 200.00,
            'grand_total' => 17224.00,
            'amount_paid' => 0.00,
            'balance_due' => 17224.00,
            'payment_terms' => 'Immediate on supply',
            'notes' => 'Follow up sent on overdue balance.',
            'terms_and_conditions' => '1.5% interest accrues monthly.',
            'status' => 'Overdue',
            'created_at' => Carbon::today()->subDays(25),
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv4->id,
            'item_id' => $createdItems[3]->id,
            'item_name' => $createdItems[3]->item_name,
            'description' => 'Precision Stainless Steel Flanges DN100 Grade 316L',
            'price' => 3800.00,
            'quantity' => 4.00,
            'gst_percentage' => 12.00,
            'gst_amount' => 1824.00,
            'line_total' => 15200.00,
            'sort_order' => 0,
        ]);
    }
}
