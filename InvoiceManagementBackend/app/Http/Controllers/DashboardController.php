<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $today = Carbon::today();
            $todayStr = $today->toDateString();
            $yesterdayStr = Carbon::yesterday()->toDateString();
            $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
            $endOfMonth = Carbon::now()->endOfMonth()->toDateString();
            $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth()->toDateString();
            $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth()->toDateString();

            // 1. Total Customers (Active)
            $totalCustomers = (int) Customer::where('status', 'active')->count();
            $lastMonthCustomers = (int) Customer::where('status', 'active')
                ->where('created_at', '<', $startOfMonth)
                ->count();
            $customerGrowthPct = $lastMonthCustomers > 0
                ? round((($totalCustomers - $lastMonthCustomers) / $lastMonthCustomers) * 100, 1)
                : 0;

            // 2. Today's Invoices
            $todayInvoicesCount = (int) Invoice::where('invoice_date', $todayStr)->count();
            $yesterdayInvoicesCount = (int) Invoice::where('invoice_date', $yesterdayStr)->count();
            $invoicesDiff = $todayInvoicesCount - $yesterdayInvoicesCount;

            // 3. Total Revenue (From recorded invoice payments ONLY, never unpaid)
            $totalRevenue = (float) (Payment::sum('amount') ?? 0);

            // 4. Monthly Revenue (Payments received during the current month)
            $monthlyRevenue = (float) (Payment::whereBetween('payment_date', [$startOfMonth, $endOfMonth])
                ->sum('amount') ?? 0);

            $lastMonthRevenue = (float) (Payment::whereBetween('payment_date', [$startOfLastMonth, $endOfLastMonth])
                ->sum('amount') ?? 0);

            $revenueGrowthPct = $lastMonthRevenue > 0
                ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
                : ($monthlyRevenue > 0 ? 100 : 0);

            // Chart 1: Daily Invoice Generation (Last 14 days) - single query
            $fourteenDaysAgo = Carbon::today()->subDays(13)->toDateString();
            $dailyCounts = Invoice::where('invoice_date', '>=', $fourteenDaysAgo)
                ->selectRaw('invoice_date, count(*) as total')
                ->groupBy('invoice_date')
                ->pluck('total', 'invoice_date')
                ->all();

            $dailyInvoices = [];
            for ($i = 13; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $dStr = $date->toDateString();
                $dailyInvoices[] = [
                    'date' => $dStr,
                    'label' => $date->format('d M'),
                    'count' => (int) ($dailyCounts[$dStr] ?? 0),
                ];
            }

            // Chart 2: Monthly Revenue (Last 6 months) - single query
            $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth()->toDateString();
            $recentPayments = Payment::where('payment_date', '>=', $sixMonthsAgo)
                ->select(['payment_date', 'amount'])
                ->get();

            $monthlyRevenueTrend = [];
            for ($m = 5; $m >= 0; $m--) {
                $monthDate = Carbon::now()->subMonths($m);
                $mStart = $monthDate->copy()->startOfMonth()->toDateString();
                $mEnd = $monthDate->copy()->endOfMonth()->toDateString();
                $mLabel = $monthDate->format('M Y');
                $mSum = $recentPayments->filter(function ($p) use ($mStart, $mEnd) {
                    $d = substr((string) $p->payment_date, 0, 10);
                    return $d >= $mStart && $d <= $mEnd;
                })->sum('amount');

                $monthlyRevenueTrend[] = [
                    'month' => $mLabel,
                    'revenue' => round((float) $mSum, 2),
                ];
            }

            // Chart 3: Invoice Payment Status Breakdown - single query
            $rawStatusCounts = Invoice::selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all();

            $statusCounts = [
                'Paid' => (int) ($rawStatusCounts['Paid'] ?? 0),
                'Partially Paid' => (int) ($rawStatusCounts['Partially Paid'] ?? 0),
                'Generated' => (int) ($rawStatusCounts['Generated'] ?? 0),
                'Draft' => (int) ($rawStatusCounts['Draft'] ?? 0),
                'Overdue' => (int) ($rawStatusCounts['Overdue'] ?? 0),
                'Cancelled' => (int) ($rawStatusCounts['Cancelled'] ?? 0),
            ];

            // 4. Recent Invoices
            $recentInvoices = Invoice::with('customer')
                ->orderBy('id', 'desc')
                ->limit(6)
                ->get();

            // 5. Recent Customers
            $recentCustomers = Customer::with('phones')
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'statistics' => [
                        'total_customers' => [
                            'value' => $totalCustomers,
                            'growth_pct' => $customerGrowthPct,
                            'comparison_text' => 'vs last month',
                        ],
                        'today_invoices' => [
                            'value' => $todayInvoicesCount,
                            'diff' => $invoicesDiff,
                            'comparison_text' => $invoicesDiff >= 0 ? "+{$invoicesDiff} vs yesterday" : "{$invoicesDiff} vs yesterday",
                        ],
                        'total_revenue' => [
                            'value' => round($totalRevenue, 2),
                            'formatted' => '₹' . number_format($totalRevenue, 2),
                            'comparison_text' => 'Lifetime verified collections',
                        ],
                        'monthly_revenue' => [
                            'value' => round($monthlyRevenue, 2),
                            'formatted' => '₹' . number_format($monthlyRevenue, 2),
                            'growth_pct' => $revenueGrowthPct,
                            'comparison_text' => 'vs previous month',
                        ],
                    ],
                    'charts' => [
                        'daily_invoices' => $dailyInvoices,
                        'monthly_revenue' => $monthlyRevenueTrend,
                        'status_distribution' => $statusCounts,
                    ],
                    'recent_invoices' => $recentInvoices,
                    'recent_customers' => $recentCustomers,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'statistics' => [
                        'total_customers' => ['value' => 0, 'growth_pct' => 0, 'comparison_text' => 'vs last month'],
                        'today_invoices' => ['value' => 0, 'diff' => 0, 'comparison_text' => '0 vs yesterday'],
                        'total_revenue' => ['value' => 0, 'formatted' => '₹0.00', 'comparison_text' => 'Lifetime verified collections'],
                        'monthly_revenue' => ['value' => 0, 'formatted' => '₹0.00', 'growth_pct' => 0, 'comparison_text' => 'vs previous month'],
                    ],
                    'charts' => [
                        'daily_invoices' => [],
                        'monthly_revenue' => [],
                        'status_distribution' => [
                            'Paid' => 0, 'Partially Paid' => 0, 'Generated' => 0, 'Draft' => 0, 'Overdue' => 0, 'Cancelled' => 0
                        ],
                    ],
                    'recent_invoices' => [],
                    'recent_customers' => [],
                ],
            ]);
        }
    }
}
