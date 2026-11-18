<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Get pending payment notifications grouped by payment term (Daily, 15 Days, Monthly).
     */
    public function pendingPayments(Request $request): JsonResponse
    {
        $today = Carbon::today();

        // Retrieve all pending invoices
        $query = Invoice::query()
            ->with(['customer'])
            ->where('balance_due', '>', 0)
            ->where('status', '!=', 'Cancelled');

        $allPending = $query->orderBy('due_date', 'asc')->get();

        $dailyList = [];
        $fifteenDaysList = [];
        $monthlyList = [];
        $otherList = [];

        foreach ($allPending as $inv) {
            $dueDate = $inv->due_date ? Carbon::parse($inv->due_date) : null;
            $isOverdue = $dueDate && $dueDate->isPast() && !$dueDate->isToday();
            $daysDiff = $dueDate ? $today->diffInDays($dueDate, false) : 0;

            $item = [
                'id' => $inv->id,
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_id' => $inv->customer_id,
                'customer_name' => $inv->customer_name,
                'company_name' => $inv->company_name,
                'customer_phone' => $inv->customer_phone,
                'customer_email' => $inv->customer_email,
                'invoice_date' => $inv->invoice_date,
                'due_date' => $inv->due_date,
                'grand_total' => (float) $inv->grand_total,
                'amount_paid' => (float) $inv->amount_paid,
                'balance_due' => (float) $inv->balance_due,
                'payment_method' => $inv->payment_method,
                'status' => $inv->status,
                'is_overdue' => $isOverdue,
                'days_diff' => $daysDiff, // Negative means overdue by X days
            ];

            $method = strtolower($inv->payment_method ?? '');
            if (str_contains($method, 'daily')) {
                $dailyList[] = $item;
            } elseif (str_contains($method, '15')) {
                $fifteenDaysList[] = $item;
            } elseif (str_contains($method, 'monthly')) {
                $monthlyList[] = $item;
            } else {
                $otherList[] = $item;
            }
        }

        $allNotifications = array_merge($dailyList, $fifteenDaysList, $monthlyList, $otherList);

        return response()->json([
            'status' => 'success',
            'counts' => [
                'total' => count($allNotifications),
                'daily' => count($dailyList),
                'fifteen_days' => count($fifteenDaysList),
                'monthly' => count($monthlyList),
            ],
            'daily' => $dailyList,
            'fifteen_days' => $fifteenDaysList,
            'monthly' => $monthlyList,
            'all' => $allNotifications,
        ]);
    }

    /**
     * Quick-approve and mark invoice payment as received from the notification area.
     */
    public function approvePayment(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            if ($invoice->balance_due <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Invoice #{$invoice->invoice_number} is already fully paid.",
                ], 400);
            }

            $amount = (float) $invoice->balance_due;
            $paymentMethod = $request->input('payment_method', $invoice->payment_method ?: 'Bank Transfer');

            $payment = $this->invoiceService->recordPayment($invoice, [
                'amount' => $amount,
                'payment_date' => date('Y-m-d'),
                'payment_method' => $paymentMethod,
                'notes' => 'Approved & marked paid via Topbar Notification Center',
                'reference_number' => 'NOTIF-APPV-' . time(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Payment of ₹" . number_format($amount, 2) . " for customer '{$invoice->customer_name}' successfully approved & marked Paid!",
                'data' => [
                    'payment' => $payment,
                    'invoice' => $invoice->fresh(['payments', 'customer']),
                    'customer_id' => $invoice->customer_id,
                    'customer_name' => $invoice->customer_name,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
