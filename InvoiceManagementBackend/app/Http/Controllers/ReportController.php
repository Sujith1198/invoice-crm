<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Standard query builder applying common report filters
     */
    protected function applyReportFilters(Request $request, $query)
    {
        if ($request->filled('from_date')) {
            $query->whereDate('invoice_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('invoice_date', '<=', $request->to_date);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                  ->orWhere('customer_name', 'like', "%{$s}%")
                  ->orWhere('company_name', 'like', "%{$s}%");
            });
        }

        if ($request->filled('item_id')) {
            $query->whereHas('items', function ($iq) use ($request) {
                $iq->where('item_id', $request->item_id);
            });
        }

        return $query;
    }

    protected function formatReportResponse($query, Request $request): JsonResponse
    {
        $clonedQuery = clone $query;

        $summary = [
            'total_invoices' => (int) $clonedQuery->count(),
            'total_subtotal' => round((float) $clonedQuery->sum('subtotal'), 2),
            'total_gst' => round((float) $clonedQuery->sum('total_gst'), 2),
            'total_grand' => round((float) $clonedQuery->sum('grand_total'), 2),
            'total_paid' => round((float) $clonedQuery->sum('amount_paid'), 2),
            'total_balance' => round((float) $clonedQuery->sum('balance_due'), 2),
        ];

        $sortField = $request->get('sort_by', 'invoice_date');
        $sortOrder = $request->get('sort_order', 'desc');
        if (in_array($sortField, ['invoice_number', 'invoice_date', 'customer_name', 'subtotal', 'total_gst', 'grand_total', 'amount_paid', 'balance_due', 'status'])) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('invoice_date', 'desc');
        }

        $perPage = (int) $request->get('per_page', 10);
        $records = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'summary' => $summary,
            'data' => $records,
        ]);
    }

    public function daily(Request $request): JsonResponse
    {
        $date = $request->get('date', Carbon::today()->toDateString());
        $query = Invoice::whereDate('invoice_date', $date);
        $this->applyReportFilters($request, $query);

        return $this->formatReportResponse($query, $request);
    }

    public function weekly(Request $request): JsonResponse
    {
        $startOfWeek = Carbon::now()->startOfWeek()->toDateString();
        $endOfWeek = Carbon::now()->endOfWeek()->toDateString();

        $query = Invoice::whereBetween('invoice_date', [
            $request->get('from_date', $startOfWeek),
            $request->get('to_date', $endOfWeek),
        ]);
        $this->applyReportFilters($request, $query);

        return $this->formatReportResponse($query, $request);
    }

    public function monthly(Request $request): JsonResponse
    {
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $query = Invoice::whereBetween('invoice_date', [
            $request->get('from_date', $startOfMonth),
            $request->get('to_date', $endOfMonth),
        ]);
        $this->applyReportFilters($request, $query);

        return $this->formatReportResponse($query, $request);
    }

    public function yearly(Request $request): JsonResponse
    {
        $startOfYear = Carbon::now()->startOfYear()->toDateString();
        $endOfYear = Carbon::now()->endOfYear()->toDateString();

        $query = Invoice::whereBetween('invoice_date', [
            $request->get('from_date', $startOfYear),
            $request->get('to_date', $endOfYear),
        ]);
        $this->applyReportFilters($request, $query);

        return $this->formatReportResponse($query, $request);
    }

    public function outstanding(Request $request): JsonResponse
    {
        $query = Invoice::where('balance_due', '>', 0)
            ->where('status', '!=', 'Cancelled');
        $this->applyReportFilters($request, $query);

        return $this->formatReportResponse($query, $request);
    }

    public function paid(Request $request): JsonResponse
    {
        $query = Invoice::where('status', 'Paid');
        $this->applyReportFilters($request, $query);

        return $this->formatReportResponse($query, $request);
    }

    public function unpaid(Request $request): JsonResponse
    {
        $query = Invoice::where('amount_paid', 0)
            ->where('status', '!=', 'Cancelled');
        $this->applyReportFilters($request, $query);

        return $this->formatReportResponse($query, $request);
    }

    public function gst(Request $request): JsonResponse
    {
        $query = Invoice::where('total_gst', '>', 0);
        $this->applyReportFilters($request, $query);

        return $this->formatReportResponse($query, $request);
    }

    public function revenue(Request $request): JsonResponse
    {
        $query = Payment::with(['invoice.customer']);

        if ($request->filled('from_date')) {
            $query->whereDate('payment_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('payment_date', '<=', $request->to_date);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('customer_id')) {
            $query->whereHas('invoice', function ($iq) use ($request) {
                $iq->where('customer_id', $request->customer_id);
            });
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('payment_number', 'like', "%{$s}%")
                  ->orWhere('reference_number', 'like', "%{$s}%")
                  ->orWhereHas('invoice', function ($iq) use ($s) {
                      $iq->where('invoice_number', 'like', "%{$s}%")
                         ->orWhere('customer_name', 'like', "%{$s}%")
                         ->orWhere('company_name', 'like', "%{$s}%");
                  });
            });
        }

        $totalRevenue = (float) (clone $query)->sum('amount');
        $totalPaymentsCount = (int) (clone $query)->count();

        $payments = $query->orderBy('payment_date', 'desc')->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => 'success',
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_transactions' => $totalPaymentsCount,
            ],
            'data' => $payments,
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        $query = Customer::withCount('invoices')
            ->withSum('invoices', 'grand_total')
            ->withSum('invoices', 'amount_paid')
            ->withSum('invoices', 'balance_due');

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('company_name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('city', 'like', "%{$s}%");
            });
        }

        $cloned = clone $query;
        $totalCustomers = (int) $cloned->count();

        $customerIds = (clone $query)->pluck('id');
        $invoicesQuery = Invoice::whereIn('customer_id', $customerIds);
        $totalInvoices = (int) (clone $invoicesQuery)->count();
        $totalGrand = round((float) (clone $invoicesQuery)->sum('grand_total'), 2);
        $totalPaid = round((float) (clone $invoicesQuery)->sum('amount_paid'), 2);
        $totalBalance = round((float) (clone $invoicesQuery)->sum('balance_due'), 2);

        $summary = [
            'total_customers' => $totalCustomers,
            'total_invoices' => $totalInvoices,
            'total_grand' => $totalGrand,
            'total_paid' => $totalPaid,
            'total_balance' => $totalBalance,
        ];

        $customers = $query->orderBy('invoices_sum_grand_total', 'desc')->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => 'success',
            'summary' => $summary,
            'data' => $customers,
        ]);
    }

    public function items(Request $request): JsonResponse
    {
        $query = Item::withCount('invoiceItems')
            ->withSum('invoiceItems', 'quantity')
            ->withSum('invoiceItems', 'line_total');

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where('item_name', 'like', "%{$s}%");
        }

        $cloned = clone $query;
        $totalItems = (int) $cloned->count();

        $itemIds = (clone $query)->pluck('id');
        $invoiceItemsQuery = InvoiceItem::whereIn('item_id', $itemIds);
        $totalBilledCount = (int) (clone $invoiceItemsQuery)->count();
        $totalQtySold = (float) (clone $invoiceItemsQuery)->sum('quantity');
        $totalSales = round((float) (clone $invoiceItemsQuery)->sum('line_total'), 2);

        $summary = [
            'total_items' => $totalItems,
            'total_billed_count' => $totalBilledCount,
            'total_quantity_sold' => $totalQtySold,
            'total_sales' => $totalSales,
        ];

        $items = $query->orderBy('invoice_items_sum_line_total', 'desc')->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => 'success',
            'summary' => $summary,
            'data' => $items,
        ]);
    }
}

