<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Item::with('customer');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('import_declaration', 'like', "%{$search}%")
                  ->orWhere('export_declaration', 'like', "%{$search}%")
                  ->orWhere('certificate_of_origin', 'like', "%{$search}%")
                  ->orWhere('xo_permit', 'like', "%{$search}%")
                  ->orWhere('amendment', 'like', "%{$search}%");
            });
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sortField = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');
        if (in_array($sortField, ['id', 'item_name', 'item_cost', 'gst_percentage', 'status', 'created_at'])) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = (int) $request->get('per_page', 10);
        $items = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        $item = Item::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Item created successfully',
            'data' => $item,
        ], 201);
    }

    public function show(Item $item): JsonResponse
    {
        $item->load('customer');

        return response()->json([
            'status' => 'success',
            'data' => $item,
        ]);
    }

    public function update(UpdateItemRequest $request, Item $item): JsonResponse
    {
        $item->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Item updated successfully',
            'data' => $item,
        ]);
    }

    public function destroy(Item $item): JsonResponse
    {
        $item->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Item deleted successfully',
        ]);
    }

    /**
     * Search endpoint for invoice items autocomplete
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->get('q', ''));
        $customerId = $request->get('customer_id');

        $itemsQuery = Item::with('customer')->where('status', 'active');

        if (!empty($customerId)) {
            $itemsQuery->where(function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)
                  ->orWhereNull('customer_id');
            });
        }

        if ($query !== '') {
            $itemsQuery->where(function ($q) use ($query) {
                $q->where('item_name', 'like', "%{$query}%")
                  ->orWhere('import_declaration', 'like', "%{$query}%")
                  ->orWhere('export_declaration', 'like', "%{$query}%");
            });
        }

        $items = $itemsQuery->orderByRaw('CASE WHEN customer_id = ? THEN 0 ELSE 1 END', [$customerId ?? 0])
            ->orderBy('item_name', 'asc')
            ->limit(30)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }
}

