<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSequence;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Exception;

class InvoiceService
{
    /**
     * Generate the next unique invoice number in format YYYYMMDD0001 without prefix
     */
    public function generateInvoiceNumber(?string $date = null): string
    {
        return DB::transaction(function () use ($date) {
            $datePrefix = !empty($date) ? date('Ymd', strtotime($date)) : date('Ymd'); // e.g. 20261118 or 20261117

            // Find highest existing invoice number for this date prefix
            $lastSeq = 0;
            $latest = Invoice::where('invoice_number', 'like', "{$datePrefix}%")
                ->orderBy('invoice_number', 'desc')
                ->first();

            if ($latest && preg_match('/^' . $datePrefix . '(\d{4})$/', $latest->invoice_number, $matches)) {
                $lastSeq = (int) $matches[1];
            } else {
                // Also check if any existing invoices end with 4 digits for this date
                $fallback = Invoice::where('invoice_number', 'like', "%{$datePrefix}%")
                    ->orderBy('id', 'desc')
                    ->first();
                if ($fallback && preg_match('/(\d{4})$/', $fallback->invoice_number, $m)) {
                    $lastSeq = (int) $m[1];
                }
            }

            $nextSeq = $lastSeq + 1;

            return sprintf('%s%04d', $datePrefix, $nextSeq);
        });
    }

    /**
     * Calculate all totals server-side safely
     */
    public function calculateTotals(array $itemsData, float $discount = 0.0, float $additionalCharges = 0.0): array
    {
        $subtotal = 0.0;
        $totalGst = 0.0;
        $calculatedItems = [];

        foreach ($itemsData as $index => $item) {
            $price = round((float) ($item['price'] ?? 0.0), 2);
            $qty = round((float) ($item['quantity'] ?? 1.0), 2);
            $gstPct = round((float) ($item['gst_percentage'] ?? 0.0), 2);

            $lineTotal = round($price * $qty, 2);
            $gstAmount = round(($lineTotal * $gstPct) / 100.0, 2);

            $subtotal += $lineTotal;
            $totalGst += $gstAmount;

            $calculatedItems[] = [
                'item_id' => $item['item_id'] ?? null,
                'item_name' => $item['item_name'] ?? 'Custom Item',
                'description' => $item['description'] ?? null,
                'price' => $price,
                'quantity' => $qty,
                'gst_percentage' => $gstPct,
                'gst_amount' => $gstAmount,
                'line_total' => $lineTotal,
                'sort_order' => $item['sort_order'] ?? $index,
            ];
        }

        $subtotal = round($subtotal, 2);
        $totalGst = round($totalGst, 2);
        $discount = round(max(0, $discount), 2);
        $additionalCharges = round(max(0, $additionalCharges), 2);

        $grandTotal = round(max(0, $subtotal + $totalGst - $discount + $additionalCharges), 2);

        return [
            'subtotal' => $subtotal,
            'total_gst' => $totalGst,
            'discount' => $discount,
            'additional_charges' => $additionalCharges,
            'grand_total' => $grandTotal,
            'items' => $calculatedItems,
        ];
    }

    /**
     * Create invoice with transaction
     */
    public function createInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoiceDate = $data['invoice_date'] ?? date('Y-m-d');
            $rawNumber = $data['invoice_number'] ?? '';

            // Clean any prefix if entered (strip INV, INVOICE, #, dashes)
            $cleanNumber = preg_replace('/^(INV|INVOICE)[-_#\s]*/i', '', $rawNumber);
            $cleanNumber = ltrim($cleanNumber, '# ');

            if (empty($cleanNumber) || !preg_match('/^\d{8}\d{4}$/', $cleanNumber)) {
                $invoiceNumber = $this->generateInvoiceNumber($invoiceDate);
            } else {
                $invoiceNumber = $cleanNumber;
            }

            // Check uniqueness
            if (Invoice::where('invoice_number', $invoiceNumber)->exists()) {
                $invoiceNumber = $this->generateInvoiceNumber($invoiceDate);
            }

            $discount = (float) ($data['discount'] ?? 0.0);
            $additionalCharges = (float) ($data['additional_charges'] ?? 0.0);
            $itemsData = $data['items'] ?? [];

            $calc = $this->calculateTotals($itemsData, $discount, $additionalCharges);

            $amountPaid = 0.0;
            $balanceDue = $calc['grand_total'];

            $status = $data['status'] ?? 'Draft';
            if ($status === 'Generated' && Carbon::parse($data['due_date'])->isPast() && $balanceDue > 0) {
                $status = 'Overdue';
            }

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $data['invoice_date'] ?? date('Y-m-d'),
                'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? 'Walk-in Customer',
                'company_name' => $data['company_name'] ?? null,
                'customer_address' => $data['customer_address'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'payment_method' => $data['payment_method'] ?? 'Monthly Pay',
                'subtotal' => $calc['subtotal'],
                'total_gst' => $calc['total_gst'],
                'discount' => $calc['discount'],
                'additional_charges' => $calc['additional_charges'],
                'grand_total' => $calc['grand_total'],
                'amount_paid' => $amountPaid,
                'balance_due' => $balanceDue,
                'payment_terms' => $data['payment_terms'] ?? 'Due on receipt',
                'notes' => $data['notes'] ?? null,
                'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
                'status' => $status,
            ]);

            foreach ($calc['items'] as $item) {
                $item['invoice_id'] = $invoice->id;
                InvoiceItem::create($item);
            }

            return $invoice->load(['items', 'customer', 'payments']);
        });
    }

    /**
     * Update invoice with transaction
     */
    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $discount = (float) ($data['discount'] ?? $invoice->discount);
            $additionalCharges = (float) ($data['additional_charges'] ?? $invoice->additional_charges);
            $itemsData = $data['items'] ?? [];

            $calc = $this->calculateTotals($itemsData, $discount, $additionalCharges);

            $amountPaid = (float) $invoice->amount_paid;
            $balanceDue = round(max(0, $calc['grand_total'] - $amountPaid), 2);

            $status = $data['status'] ?? $invoice->status;
            if ($status !== 'Draft' && $status !== 'Cancelled') {
                if ($balanceDue <= 0 && $calc['grand_total'] > 0) {
                    $status = 'Paid';
                } elseif ($amountPaid > 0) {
                    $status = 'Partially Paid';
                } elseif (Carbon::parse($data['due_date'] ?? $invoice->due_date)->isPast()) {
                    $status = 'Overdue';
                } else {
                    $status = 'Generated';
                }
            }

            $updateData = [
                'invoice_date' => $data['invoice_date'] ?? $invoice->invoice_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'customer_id' => array_key_exists('customer_id', $data) ? $data['customer_id'] : $invoice->customer_id,
                'customer_name' => $data['customer_name'] ?? $invoice->customer_name,
                'company_name' => $data['company_name'] ?? $invoice->company_name,
                'customer_address' => $data['customer_address'] ?? $invoice->customer_address,
                'customer_phone' => $data['customer_phone'] ?? $invoice->customer_phone,
                'customer_email' => $data['customer_email'] ?? $invoice->customer_email,
                'payment_method' => $data['payment_method'] ?? $invoice->payment_method,
                'subtotal' => $calc['subtotal'],
                'total_gst' => $calc['total_gst'],
                'discount' => $calc['discount'],
                'additional_charges' => $calc['additional_charges'],
                'grand_total' => $calc['grand_total'],
                'amount_paid' => $amountPaid,
                'balance_due' => $balanceDue,
                'payment_terms' => $data['payment_terms'] ?? $invoice->payment_terms,
                'notes' => $data['notes'] ?? $invoice->notes,
                'terms_and_conditions' => $data['terms_and_conditions'] ?? $invoice->terms_and_conditions,
                'status' => $status,
            ];

            if (!empty($data['invoice_number'])) {
                $cleanNumber = preg_replace('/^(INV|INVOICE)[-_#\s]*/i', '', $data['invoice_number']);
                $cleanNumber = ltrim($cleanNumber, '# ');
                if (!empty($cleanNumber)) {
                    $updateData['invoice_number'] = $cleanNumber;
                }
            }

            $invoice->update($updateData);

            // Replace line items
            $invoice->items()->delete();
            foreach ($calc['items'] as $item) {
                $item['invoice_id'] = $invoice->id;
                InvoiceItem::create($item);
            }

            return $invoice->fresh(['items', 'customer', 'payments']);
        });
    }

    /**
     * Record payment against an invoice
     */
    public function recordPayment(Invoice $invoice, array $paymentData): Payment
    {
        return DB::transaction(function () use ($invoice, $paymentData) {
            $amount = round((float) $paymentData['amount'], 2);

            if ($amount <= 0) {
                throw new Exception('Payment amount must be greater than zero.');
            }

            if ($amount > $invoice->balance_due + 0.01) {
                throw new Exception("Payment amount exceeds balance due of {$invoice->balance_due}.");
            }

            $year = date('Y');
            $paymentCount = Payment::whereYear('created_at', $year)->count() + 1;
            $paymentNumber = sprintf('PAY-%d-%04d', $year, $paymentCount);

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payment_number' => $paymentNumber,
                'payment_date' => $paymentData['payment_date'] ?? date('Y-m-d'),
                'amount' => $amount,
                'payment_method' => $paymentData['payment_method'] ?? 'Bank Transfer',
                'reference_number' => $paymentData['reference_number'] ?? null,
                'notes' => $paymentData['notes'] ?? null,
            ]);

            $newAmountPaid = round($invoice->amount_paid + $amount, 2);
            $newBalanceDue = round(max(0, $invoice->grand_total - $newAmountPaid), 2);

            $status = $newBalanceDue <= 0.00 ? 'Paid' : 'Partially Paid';

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'balance_due' => $newBalanceDue,
                'status' => $status,
            ]);

            return $payment;
        });
    }

    /**
     * Generate PDF binary
     */
    public function generatePdf(Invoice $invoice)
    {
        $settings = CompanySetting::first() ?? new CompanySetting([
            'company_name' => 'Orange Invoicing Solutions',
            'company_address' => '123 Business Avenue, Tech Park, Bangalore, India',
            'phone_number' => '+91 98765 43210',
            'email' => 'billing@orangeinvoice.com',
            'gstin' => '29AAAAA0000A1Z5',
            'default_currency' => 'INR',
            'default_currency_symbol' => '₹',
        ]);

        $invoice->load(['items', 'payments', 'customer']);

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'settings' => $settings,
            'currencySymbol' => $settings->default_currency_symbol ?: '₹',
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        return $pdf;
    }

    /**
     * Dispatch invoice email with PDF attachment to customer's first email.
     */
    public function sendInvoiceEmail(Invoice $invoice, array $options = []): array
    {
        $invoice->load('customer');
        $liveCustomerEmail = $invoice->customer?->email;
        $rawEmail = !empty($options['email'])
            ? $options['email']
            : ($liveCustomerEmail ?: ($invoice->customer_email ?? ''));

        // Keep invoice snapshot in sync with customer live email
        if (!empty($liveCustomerEmail) && $invoice->customer_email !== $liveCustomerEmail) {
            $invoice->update(['customer_email' => $liveCustomerEmail]);
        }

        // Extract all valid email addresses (handles commas, semicolons, whitespace, slashes)
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $rawEmail, $matches);
        $detectedEmails = !empty($matches[0]) ? array_values(array_unique($matches[0])) : [];

        if (empty($detectedEmails)) {
            throw new Exception("No valid email address found for customer '{$invoice->customer_name}'.");
        }

        // Always select the first email when multiple are available
        $primaryEmail = $detectedEmails[0];

        $settings = CompanySetting::first() ?? new CompanySetting([
            'company_name' => 'Orange Invoicing & Logistics Ltd',
            'email' => 'contact@orangeinvoice.com',
            'invoice_footer' => 'Thank you for choosing Orange Invoicing & Logistics Ltd.',
        ]);

        $companyName = $settings->company_name ?: 'Orange Invoicing & Logistics Ltd';

        // Generate clean number & PDF
        $cleanNumber = preg_replace('/^(INV|INVOICE)[-_#\s]*/i', '', $invoice->invoice_number);
        $cleanNumber = ltrim($cleanNumber, '# ');

        $pdf = $this->generatePdf($invoice);
        $pdfBinary = $pdf->output();
        $filename = "Invoice-{$cleanNumber}.pdf";

        $subject = !empty($options['subject'])
            ? $options['subject']
            : "Tax Invoice #{$cleanNumber} from {$companyName}";

        $customNote = !empty($options['message'])
            ? nl2br(e($options['message']))
            : 'Please find attached your official tax invoice in PDF format. Thank you for your business!';

        $formattedTotal = number_format((float) $invoice->grand_total, 2);
        $formattedBalance = number_format((float) $invoice->balance_due, 2);
        $formattedDate = date('d M, Y', strtotime($invoice->invoice_date));
        $formattedDueDate = date('d M, Y', strtotime($invoice->due_date));
        $terms = $invoice->payment_terms ?: 'Standard Trade Terms';
        $footerText = $settings->invoice_footer ?: 'Thank you for choosing Orange Invoicing & Logistics Ltd.';

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.06); }
        .header { background: #FF6200; background: linear-gradient(135deg, #FFAB00 0%, #FF6200 100%); padding: 24px 28px; }
        .header h1 { margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .header p { margin: 4px 0 0 0; color: #fff8e1; font-size: 12.5px; }
        .content { padding: 28px; }
        .greeting { font-size: 15px; font-weight: 600; color: #0f172a; margin-bottom: 12px; }
        .message { font-size: 13.5px; color: #475569; line-height: 1.6; margin-bottom: 20px; }
        .attachment-pill { display: inline-block; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 7px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 20px; }
        .invoice-box { background: #fffaf0; border: 1px solid #fed7aa; border-radius: 10px; padding: 18px 20px; margin-bottom: 20px; }
        .detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .detail-table td { padding: 4px 0; }
        .detail-label { color: #64748b; }
        .detail-val { text-align: right; font-weight: 600; color: #0f172a; }
        .divider { border-top: 1px solid #fed7aa; padding-top: 8px; margin-top: 6px; }
        .total-val { font-size: 15px; font-weight: 700; color: #FF6200; }
        .balance-val { font-size: 14px; font-weight: 700; color: #dc2626; }
        .footer { background: #f8fafc; border-top: 1px solid #f1f5f9; padding: 16px 28px; text-align: center; font-size: 12px; color: #64748b; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>{$companyName}</h1>
            <p>Invoicing & Customer Management System</p>
        </div>
        <div class="content">
            <div class="greeting">Dear {$invoice->customer_name},</div>
            <div class="message">{$customNote}</div>

            <div class="attachment-pill">
                📎 Attached PDF: <strong>{$filename}</strong>
            </div>

            <div class="invoice-box">
                <table class="detail-table">
                    <tr>
                        <td class="detail-label">Invoice Number:</td>
                        <td class="detail-val" style="color: #FF6200; font-weight: 700;">#{$cleanNumber}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Invoice Date:</td>
                        <td class="detail-val">{$formattedDate}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Due Date:</td>
                        <td class="detail-val">{$formattedDueDate}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Payment Terms:</td>
                        <td class="detail-val">{$terms}</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Status:</td>
                        <td class="detail-val">{$invoice->status}</td>
                    </tr>
                    <tr class="divider">
                        <td class="detail-label" style="font-weight: 600; color: #1e293b;">Grand Total:</td>
                        <td class="detail-val total-val">₹{$formattedTotal}</td>
                    </tr>
                    <tr>
                        <td class="detail-label" style="font-weight: 600; color: #dc2626;">Balance Due:</td>
                        <td class="detail-val balance-val">₹{$formattedBalance}</td>
                    </tr>
                </table>
            </div>

            <p style="font-size: 12.5px; color: #64748b; margin: 18px 0 0 0;">
                If you have questions regarding this invoice or require payment assistance, please reply to this email.
            </p>
        </div>
        <div class="footer">
            {$footerText}<br>
            &copy; {$companyName}. All rights reserved.
        </div>
    </div>
</body>
</html>
HTML;

        // Dispatch email via configured SMTP
        try {
            Mail::html($htmlBody, function ($message) use ($primaryEmail, $subject, $pdfBinary, $filename, $settings, $companyName) {
                $message->to($primaryEmail)
                        ->subject($subject)
                        ->attachData($pdfBinary, $filename, [
                            'mime' => 'application/pdf',
                        ]);

                $fromEmail = !empty($settings->email) ? $settings->email : config('mail.from.address', 'sujithp249@gmail.com');
                $message->from($fromEmail, $companyName);
                $message->replyTo($fromEmail, $companyName);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("SMTP dispatch warning: " . $e->getMessage());

            // Fallback to log transport so invoice workflow doesn't fail
            Mail::mailer('log')->html($htmlBody, function ($message) use ($primaryEmail, $subject, $pdfBinary, $filename, $settings, $companyName) {
                $message->to($primaryEmail)
                        ->subject($subject)
                        ->attachData($pdfBinary, $filename, [
                            'mime' => 'application/pdf',
                        ]);
                $fromEmail = !empty($settings->email) ? $settings->email : 'sujithp249@gmail.com';
                $message->from($fromEmail, $companyName);
                $message->replyTo($fromEmail, $companyName);
            });

            if (empty(env('MAIL_PASSWORD'))) {
                return [
                    'recipient' => $primaryEmail,
                    'all_detected_emails' => $detectedEmails,
                    'filename' => $filename,
                    'subject' => $subject,
                    'invoice_number' => $cleanNumber,
                    'note' => 'SMTP configured with sujithp249@gmail.com. Please provide your Google App Password in .env (MAIL_PASSWORD) for live delivery.',
                ];
            }

            $errorMsg = $e->getMessage();
            if (str_contains($errorMsg, '535') || str_contains($errorMsg, 'BadCredentials')) {
                throw new Exception("Google SMTP Rejected (BadCredentials): Google did not accept the App Password for sujithp249@gmail.com. Please ensure 2-Step Verification is enabled and the App Password was generated while logged into sujithp249@gmail.com at https://myaccount.google.com/apppasswords.");
            }

            throw new Exception("SMTP Error: " . $errorMsg);
        }

        return [
            'recipient' => $primaryEmail,
            'all_detected_emails' => $detectedEmails,
            'filename' => $filename,
            'subject' => $subject,
            'invoice_number' => $cleanNumber,
        ];
    }
}

