<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::with('phones');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhereHas('phones', function ($pq) use ($search) {
                      $pq->where('phone_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sortField = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');
        if (in_array($sortField, ['id', 'first_name', 'last_name', 'company_name', 'email', 'city', 'payment_method', 'status', 'created_at'])) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = (int) $request->get('per_page', 10);
        $customers = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $customers,
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();

        $customer = DB::transaction(function () use ($data) {
            $phones = $data['phones'];
            unset($data['phones']);

            $customer = Customer::create($data);

            $hasPrimary = false;
            foreach ($phones as $phone) {
                $isPrimary = !empty($phone['is_primary']) && !$hasPrimary;
                if ($isPrimary) {
                    $hasPrimary = true;
                }

                CustomerPhone::create([
                    'customer_id' => $customer->id,
                    'phone_number' => $phone['phone_number'],
                    'is_primary' => $isPrimary,
                    'type' => $phone['type'] ?? 'Mobile',
                ]);
            }

            // Ensure at least one primary
            if (!$hasPrimary && $customer->phones()->count() > 0) {
                $customer->phones()->first()->update(['is_primary' => true]);
            }

            // Automatically sync updated customer email, phone, name, and company to existing invoices
            $customerName = trim($customer->first_name . ' ' . $customer->last_name);
            $primaryPhone = $customer->phones()->where('is_primary', true)->value('phone_number') ?? $customer->phones()->value('phone_number');
            $address = trim($customer->address_line1 . ($customer->address_line2 ? ', ' . $customer->address_line2 : '') . ($customer->city ? ', ' . $customer->city : ''));

            \App\Models\Invoice::where('customer_id', $customer->id)->update([
                'customer_name' => $customerName,
                'customer_email' => $customer->email,
                'customer_phone' => $primaryPhone,
                'company_name' => $customer->company_name,
                'customer_address' => $address ?: null,
            ]);

            return $customer->load('phones');
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Customer created successfully',
            'data' => $customer,
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load([
            'phones',
            'invoices' => function ($q) {
                $q->orderBy('invoice_date', 'desc')->with('payments');
            },
        ]);

        $totalInvoiced = (float) $customer->invoices->sum('grand_total');
        $totalPaid = (float) $customer->invoices->sum('amount_paid');
        $outstanding = (float) $customer->invoices->sum('balance_due');

        return response()->json([
            'status' => 'success',
            'data' => [
                'customer' => $customer,
                'metrics' => [
                    'total_invoiced' => $totalInvoiced,
                    'total_paid' => $totalPaid,
                    'outstanding_balance' => $outstanding,
                    'invoice_count' => $customer->invoices->count(),
                ],
            ],
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->validated();

        $customer = DB::transaction(function () use ($customer, $data) {
            $phones = $data['phones'];
            unset($data['phones']);

            $customer->update($data);

            // Synchronize phones
            $customer->phones()->delete();

            $hasPrimary = false;
            foreach ($phones as $phone) {
                $isPrimary = !empty($phone['is_primary']) && !$hasPrimary;
                if ($isPrimary) {
                    $hasPrimary = true;
                }

                CustomerPhone::create([
                    'customer_id' => $customer->id,
                    'phone_number' => $phone['phone_number'],
                    'is_primary' => $isPrimary,
                    'type' => $phone['type'] ?? 'Mobile',
                ]);
            }

            if (!$hasPrimary && $customer->phones()->count() > 0) {
                $customer->phones()->first()->update(['is_primary' => true]);
            }

            // Automatically sync updated customer email, phone, name, and company to existing invoices
            $customerName = trim($customer->first_name . ' ' . $customer->last_name);
            $primaryPhone = $customer->phones()->where('is_primary', true)->value('phone_number') ?? $customer->phones()->value('phone_number');
            $address = trim($customer->address_line1 . ($customer->address_line2 ? ', ' . $customer->address_line2 : '') . ($customer->city ? ', ' . $customer->city : ''));

            \App\Models\Invoice::where('customer_id', $customer->id)->update([
                'customer_name' => $customerName,
                'customer_email' => $customer->email,
                'customer_phone' => $primaryPhone,
                'company_name' => $customer->company_name,
                'customer_address' => $address ?: null,
            ]);

            return $customer->load('phones');
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Customer updated successfully',
            'data' => $customer,
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Customer deleted successfully',
        ]);
    }

    /**
     * Search endpoint for Customer Autocomplete
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->get('q', ''));

        $customers = Customer::with('phones')
            ->where('status', 'active')
            ->where(function ($q) use ($query) {
                if ($query !== '') {
                    $q->where('first_name', 'like', "%{$query}%")
                      ->orWhere('last_name', 'like', "%{$query}%")
                      ->orWhere('company_name', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%")
                      ->orWhereHas('phones', function ($pq) use ($query) {
                          $pq->where('phone_number', 'like', "%{$query}%");
                      });
                }
            })
            ->limit(15)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $customers,
        ]);
    }

    /**
     * Distinct cities for dropdown filtering
     */
    public function cities(): JsonResponse
    {
        $cities = Customer::distinct()
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->orderBy('city')
            ->pluck('city');

        return response()->json([
            'status' => 'success',
            'data' => $cities,
        ]);
    }

    /**
     * Check for duplicate customers before submit (Warning check)
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $email = $request->get('email');
        $phone = $request->get('phone');
        $excludeId = $request->get('exclude_id');

        $duplicateQuery = Customer::query();

        if ($excludeId) {
            $duplicateQuery->where('id', '!=', $excludeId);
        }

        $duplicate = null;
        if (!empty($email)) {
            $duplicate = (clone $duplicateQuery)->where('email', $email)->first();
        }

        if (!$duplicate && !empty($phone)) {
            $duplicate = (clone $duplicateQuery)->whereHas('phones', function ($q) use ($phone) {
                $q->where('phone_number', $phone);
            })->first();
        }

        return response()->json([
            'is_duplicate' => (bool) $duplicate,
            'duplicate_customer' => $duplicate ? [
                'id' => $duplicate->id,
                'name' => $duplicate->full_name,
                'email' => $duplicate->email,
                'company_name' => $duplicate->company_name,
            ] : null,
        ]);
    }
}

