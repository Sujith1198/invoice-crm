<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'DejaVu Sans', sans-serif;
            color: #242424;
        }
        body {
            padding: 36px 42px;
            background: #ffffff;
            font-size: 12px;
            line-height: 1.5;
            font-family: 'DejaVu Sans', sans-serif;
        }
        .header-table {
            width: 100%;
            margin-bottom: 24px;
            border-bottom: 3px solid #FFAB00;
            padding-bottom: 18px;
        }
        .logo-text {
            font-size: 17px;
            font-weight: 800;
            color: #FF6200;
            letter-spacing: -0.2px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .logo-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color: #FFAB00;
            border-radius: 50%;
            margin-right: 6px;
            vertical-align: middle;
        }
        .logo-tag {
            font-size: 10.5px;
            color: #666666;
            margin-top: 2px;
        }
        .invoice-title {
            text-align: right;
            font-size: 20px;
            font-weight: 800;
            color: #242424;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .invoice-number {
            text-align: right;
            font-size: 15px;
            font-weight: 700;
            color: #F77F23;
            margin-top: 3px;
            letter-spacing: 0.5px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 12px;
            background: #FFF3E0;
            color: #FF6200;
            border: 1px solid #FFAB00;
            margin-top: 4px;
        }
        .status-paid {
            background: #E8F5E9;
            color: #2E7D32;
            border-color: #A5D6A7;
        }
        .info-table {
            width: 100%;
            margin-bottom: 24px;
        }
        .info-col {
            vertical-align: top;
            width: 50%;
        }
        .section-label {
            font-size: 10px;
            font-weight: 700;
            color: #888888;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
        }
        .entity-name {
            font-size: 15px;
            font-weight: 700;
            color: #242424;
            margin-bottom: 4px;
        }
        .entity-detail {
            font-size: 12px;
            color: #555555;
            line-height: 1.4;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 3px 0;
            font-size: 12px;
        }
        .meta-label {
            color: #666666;
            font-weight: 600;
            width: 45%;
        }
        .meta-val {
            text-align: right;
            font-weight: 700;
            color: #242424;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .items-table th {
            background: #FFAB00;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            text-align: left;
        }
        .items-table th.text-right, .items-table td.text-right {
            text-align: right;
        }
        .items-table th.text-center, .items-table td.text-center {
            text-align: center;
        }
        .items-table td {
            padding: 11px 12px;
            border-bottom: 1px solid #EAEAEA;
            font-size: 12px;
            vertical-align: top;
        }
        .items-table tr:nth-child(even) td {
            background: #FAFAFA;
        }
        .item-name {
            font-weight: 700;
            color: #242424;
            font-size: 12px;
        }
        .item-desc {
            font-size: 11px;
            color: #777777;
            margin-top: 3px;
        }
        .summary-container {
            width: 100%;
            margin-bottom: 24px;
        }
        .summary-left {
            width: 55%;
            vertical-align: top;
            padding-right: 20px;
        }
        .summary-right {
            width: 45%;
            vertical-align: top;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 12px;
            font-size: 12px;
        }
        .totals-table tr.grand-row td {
            background: #FFF8E1;
            border-top: 2px solid #FFAB00;
            border-bottom: 2px solid #FFAB00;
            font-size: 15px;
            font-weight: 800;
            color: #FF6200;
            padding: 10px 12px;
        }
        .totals-table tr.balance-row td {
            font-size: 13px;
            font-weight: 700;
            color: #C62828;
            padding-top: 8px;
        }
        .note-box {
            background: #FDFBF7;
            border-left: 3px solid #F77F23;
            padding: 10px 14px;
            border-radius: 4px;
            margin-bottom: 14px;
        }
        .note-title {
            font-size: 11px;
            font-weight: 700;
            color: #F77F23;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .note-content {
            font-size: 11px;
            color: #555555;
            line-height: 1.4;
        }
        .footer-note {
            text-align: center;
            font-size: 11px;
            color: #888888;
            margin-top: 30px;
            border-top: 1px solid #EAEAEA;
            padding-top: 14px;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="vertical-align: top; width: 64%;">
                <div class="logo-text"><span class="logo-dot"></span>{{ $settings->company_name ?? 'ORANGE INVOICE' }}</div>
                <div class="logo-tag">Invoicing & Customer Management System</div>
                <div class="entity-detail" style="margin-top: 6px;">
                    {{ $settings->company_address ?? 'Commercial Tech Complex, India' }}<br>
                    Phone: {{ $settings->phone_number ?? '+91 98765 43210' }} | Email: {{ $settings->email ?? 'support@orangeinvoice.com' }}<br>
                    @if(!empty($settings->gstin))
                        <strong>GSTIN:</strong> {{ $settings->gstin }}
                    @endif
                </div>
            </td>
            <td style="vertical-align: top; width: 36%; text-align: right;">
                <div class="invoice-title">TAX INVOICE</div>
                @php
                    $cleanInvoiceNumber = preg_replace('/^(INV|INVOICE)[-_#\s]*/i', '', $invoice->invoice_number);
                    $cleanInvoiceNumber = ltrim($cleanInvoiceNumber, '# ');
                @endphp
                <div class="invoice-number">{{ $cleanInvoiceNumber }}</div>
                <div style="margin-top: 6px;">
                    <span class="status-badge {{ $invoice->status === 'Paid' ? 'status-paid' : '' }}">
                        {{ $invoice->status }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Bill To & Metadata -->
    <table class="info-table">
        <tr>
            <td class="info-col">
                <div class="section-label">Billed To</div>
                <div class="entity-name">{{ $invoice->customer_name }}</div>
                @if(!empty($invoice->company_name))
                    <div style="font-weight: 600; color: #444; font-size: 12px;">{{ $invoice->company_name }}</div>
                @endif
                <div class="entity-detail">
                    {!! nl2br(e($invoice->customer_address)) !!}<br>
                    @if(!empty($invoice->customer_phone))
                        Phone: {{ $invoice->customer_phone }}<br>
                    @endif
                    @if(!empty($invoice->customer_email))
                        Email: {{ $invoice->customer_email }}<br>
                    @endif
                    @if(!empty($invoice->payment_method))
                        Payment Method: <strong>{{ $invoice->payment_method }}</strong>
                    @endif
                </div>
            </td>
            <td class="info-col" style="padding-left: 30px;">
                <div class="section-label">Invoice Details</div>
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Invoice Date:</td>
                        <td class="meta-val">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M, Y') }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Due Date:</td>
                        <td class="meta-val">{{ \Carbon\Carbon::parse($invoice->due_date)->format('d M, Y') }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Payment Terms:</td>
                        <td class="meta-val">{{ $invoice->payment_terms ?? 'Due on Receipt' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Currency:</td>
                        <td class="meta-val">{{ $settings->default_currency ?? 'INR' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Line Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 6%;" class="text-center">S.No</th>
                <th style="width: 32%;">Item</th>
                <th style="width: 26%;">Proceed / Description</th>
                <th style="width: 12%;" class="text-right">Price</th>
                <th style="width: 8%;" class="text-center">Qty</th>
                <th style="width: 16%;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <div class="item-name">{{ $item->item_name }}</div>
                    </td>
                    <td>
                        <div class="item-desc">{{ $item->description ?? '-' }}</div>
                        @if($item->gst_percentage > 0)
                            <div style="font-size: 10px; color: #888;">GST: {{ $item->gst_percentage }}% ({{ number_format($item->gst_amount, 2) }})</div>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($item->price, 2) }}</td>
                    <td class="text-center">{{ number_format($item->quantity, 0) }}</td>
                    <td class="text-right" style="font-weight: 700;">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Summary Section -->
    <table class="summary-container">
        <tr>
            <td class="summary-left">
                @if(!empty($invoice->notes))
                    <div class="note-box">
                        <div class="note-title">Notes / Remarks</div>
                        <div class="note-content">{{ $invoice->notes }}</div>
                    </div>
                @endif

                @if(!empty($invoice->terms_and_conditions))
                    <div class="note-box">
                        <div class="note-title">Terms & Conditions</div>
                        <div class="note-content">{{ $invoice->terms_and_conditions }}</div>
                    </div>
                @endif

                @if($invoice->payments->count() > 0)
                    <div style="margin-top: 10px;">
                        <div class="section-label">Payment History</div>
                        <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid #ddd;">
                                    <th style="text-align: left; padding: 4px;">Date</th>
                                    <th style="text-align: left; padding: 4px;">Ref / Method</th>
                                    <th style="text-align: right; padding: 4px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->payments as $pay)
                                    <tr style="border-bottom: 1px solid #eee;">
                                        <td style="padding: 4px;">{{ \Carbon\Carbon::parse($pay->payment_date)->format('d M, Y') }}</td>
                                        <td style="padding: 4px;">{{ $pay->payment_number }} ({{ $pay->payment_method }})</td>
                                        <td style="padding: 4px; text-align: right; font-weight: 600;">{{ number_format($pay->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </td>
            <td class="summary-right">
                <table class="totals-table">
                    <tr>
                        <td style="color: #666;">Subtotal:</td>
                        <td style="text-align: right; font-weight: 600;">{{ number_format($invoice->subtotal, 2) }}</td>
                    </tr>
                    @if($invoice->total_gst > 0)
                        <tr>
                            <td style="color: #666;">Total GST:</td>
                            <td style="text-align: right; font-weight: 600;">{{ number_format($invoice->total_gst, 2) }}</td>
                        </tr>
                    @endif
                    @if($invoice->discount > 0)
                        <tr>
                            <td style="color: #2E7D32;">Discount (-):</td>
                            <td style="text-align: right; font-weight: 600; color: #2E7D32;">- {{ number_format($invoice->discount, 2) }}</td>
                        </tr>
                    @endif
                    @if($invoice->additional_charges > 0)
                        <tr>
                            <td style="color: #666;">Additional Charges (+):</td>
                            <td style="text-align: right; font-weight: 600;">{{ number_format($invoice->additional_charges, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="grand-row">
                        <td>Grand Total:</td>
                        <td style="text-align: right;">{{ number_format($invoice->grand_total, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="color: #2E7D32; font-weight: 600;">Amount Paid:</td>
                        <td style="text-align: right; font-weight: 700; color: #2E7D32;">{{ number_format($invoice->amount_paid, 2) }}</td>
                    </tr>
                    <tr class="balance-row">
                        <td>Balance Due:</td>
                        <td style="text-align: right;">{{ number_format($invoice->balance_due, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        {{ $settings->invoice_footer ?? 'Thank you for your business! For inquiries, please contact ' . ($settings->email ?? 'billing@orangeinvoice.com') }}
    </div>

</body>
</html>
