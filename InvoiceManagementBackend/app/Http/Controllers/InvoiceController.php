<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordPaymentRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['customer', 'payments']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('invoice_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('invoice_date', '<=', $request->to_date);
        }

        $sortField = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');
        if (in_array($sortField, ['id', 'invoice_number', 'invoice_date', 'due_date', 'grand_total', 'amount_paid', 'balance_due', 'status'])) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = (int) $request->get('per_page', 10);
        $invoices = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $invoices,
        ]);
    }

    public function nextNumber(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $number = $this->invoiceService->generateInvoiceNumber($date);

        return response()->json([
            'status' => 'success',
            'data' => [
                'next_invoice_number' => $number,
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->createInvoice($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Invoice created successfully',
                'data' => $invoice,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['items.item', 'customer.phones', 'payments']);

        return response()->json([
            'status' => 'success',
            'data' => $invoice,
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $updated = $this->invoiceService->updateInvoice($invoice, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Invoice updated successfully',
                'data' => $updated,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $invoice->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice deleted successfully',
        ]);
    }

    public function pdf(Invoice $invoice)
    {
        $pdf = $this->invoiceService->generatePdf($invoice);

        $filename = "Invoice-{$invoice->invoice_number}.pdf";

        return $pdf->stream($filename);
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $payment = $this->invoiceService->recordPayment($invoice, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Payment recorded successfully',
                'data' => [
                    'payment' => $payment,
                    'invoice' => $invoice->fresh(['payments', 'items', 'customer']),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function sendEmail(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            $options = $request->only(['email', 'subject', 'message']);
            $result = $this->invoiceService->sendInvoiceEmail($invoice, $options);

            $multiNote = (count($result['all_detected_emails']) > 1) ? " (first of multiple detected emails)" : "";

            return response()->json([
                'status' => 'success',
                'message' => "Invoice #{$result['invoice_number']} sent successfully to {$result['recipient']}{$multiNote}.",
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

