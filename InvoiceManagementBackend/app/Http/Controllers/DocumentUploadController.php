<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DocumentUpload;
use App\Models\Invoice;
use App\Models\Item;
use App\Services\DocumentExtractionService;
use App\Services\InvoiceService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentUploadController extends Controller
{
    public function __construct(
        protected DocumentExtractionService $extractionService,
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Display a listing of document uploads (Upload History).
     */
    public function index(Request $request): JsonResponse
    {
        $query = DocumentUpload::with(['invoice', 'user'])->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(extracted_data, '$.invoice.document_number')) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(extracted_data, '$.customer.first_name')) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(extracted_data, '$.customer.company_name')) LIKE ?", ["%{$search}%"]);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $perPage = (int) $request->input('per_page', 10);
        $documents = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $documents,
        ]);
    }

    /**
     * Upload and extract information from a PDF document.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'document' => 'required|file|max:20480', // max 20MB
            'confirm_duplicate' => 'nullable|boolean',
        ]);

        $file = $request->file('document');

        // Security check 1: Validate MIME type
        $mime = $file->getMimeType();
        if (!in_array($mime, ['application/pdf', 'application/x-pdf', 'application/acrobat'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid file format. Only legitimate PDF documents are supported.',
            ], 422);
        }

        // Security check 2: Validate magic bytes (%PDF-)
        $handle = fopen($file->getRealPath(), 'rb');
        $header = fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid PDF content. The uploaded file is corrupted or not a valid PDF.',
            ], 422);
        }

        // Compute SHA256 file hash for duplicate detection
        $fileHash = hash_file('sha256', $file->getRealPath());

        // Check for duplicate document
        $existingDuplicate = DocumentUpload::where('file_hash', $fileHash)->first();
        if ($existingDuplicate && !$request->boolean('confirm_duplicate')) {
            return response()->json([
                'status' => 'duplicate_warning',
                'message' => "This document has already been uploaded previously as '{$existingDuplicate->original_name}'. Generating a new invoice may result in a duplicate.",
                'duplicate_id' => $existingDuplicate->id,
                'existing_upload' => $existingDuplicate->load('invoice'),
            ], 409);
        }

        // Generate safe unique filename
        $originalName = $file->getClientOriginalName();
        $safeOriginalName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $originalName);
        $uniqueName = time() . '_' . uniqid() . '_' . $safeOriginalName;

        // Store file securely in storage/app/documents (outside public folder)
        $storedPath = $file->storeAs('documents', $uniqueName);
        $fullPath = $this->getStorageFullPath($storedPath);

        // Create record with initial status
        $documentUpload = DocumentUpload::create([
            'file_name' => $uniqueName,
            'original_name' => $originalName,
            'file_path' => $storedPath,
            'file_size' => $file->getSize(),
            'file_hash' => $fileHash,
            'mime_type' => $mime,
            'status' => 'Uploaded',
            'user_id' => $request->user()?->id,
        ]);

        // Process extraction
        try {
            $documentUpload->update(['status' => 'Reading PDF']);
            $documentUpload->update(['status' => 'Extracting Information']);

            $extracted = $this->extractionService->extractFromFile($fullPath);

            $documentUpload->update([
                'status' => 'Review Required',
                'extracted_data' => $extracted,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'PDF uploaded and parsed successfully. Ready for review.',
                'data' => $documentUpload->fresh(['invoice']),
            ], 201);
        } catch (\Throwable $e) {
            Log::error("Document extraction error for upload #{$documentUpload->id}: " . $e->getMessage());

            $documentUpload->update([
                'status' => 'Failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to parse PDF document: ' . $e->getMessage(),
                'data' => $documentUpload,
            ], 422);
        }
    }

    /**
     * Retrieve single document upload details.
     */
    public function show(DocumentUpload $document): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $document->load(['invoice.items', 'user']),
        ]);
    }

    /**
     * Update extracted data after manual review/edit by the user.
     */
    public function updateExtraction(Request $request, DocumentUpload $document): JsonResponse
    {
        $validated = $request->validate([
            'customer' => 'required|array',
            'invoice' => 'required|array',
            'items' => 'required|array',
            'totals' => 'required|array',
            'customer_match' => 'nullable|array',
        ]);

        $data = $document->extracted_data ?? [];
        $data['customer'] = $validated['customer'];
        $data['invoice'] = $validated['invoice'];
        $data['items'] = $validated['items'];
        $data['totals'] = $validated['totals'];
        if (isset($validated['customer_match'])) {
            $data['customer_match'] = $validated['customer_match'];
        }

        $document->update([
            'extracted_data' => $data,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Extracted data updated successfully.',
            'data' => $document->fresh(['invoice']),
        ]);
    }

    /**
     * Retry extraction on an existing document.
     */
    public function retry(DocumentUpload $document): JsonResponse
    {
        $fullPath = $this->getStorageFullPath($document->file_path);
        if (!file_exists($fullPath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Physical document file not found on disk.',
            ], 404);
        }

        try {
            $document->update(['status' => 'Reading PDF', 'error_message' => null]);
            $document->update(['status' => 'Extracting Information']);

            $extracted = $this->extractionService->extractFromFile($fullPath);

            $document->update([
                'status' => 'Review Required',
                'extracted_data' => $extracted,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Document re-parsed successfully.',
                'data' => $document->fresh(['invoice']),
            ]);
        } catch (\Throwable $e) {
            $document->update([
                'status' => 'Failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Re-extraction failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate a Draft Invoice from reviewed extraction data.
     */
    public function generateDraft(Request $request, DocumentUpload $document): JsonResponse
    {
        // Require user confirmation if document already has an active generated invoice
        if ($document->invoice_id && !$request->boolean('force_regenerate')) {
            $existingInvoice = Invoice::find($document->invoice_id);
            if ($existingInvoice) {
                return response()->json([
                    'status' => 'duplicate_warning',
                    'message' => "A draft invoice #{$existingInvoice->invoice_number} is already associated with this document.",
                    'invoice' => $existingInvoice,
                ], 409);
            }
        }

        $payload = $request->all();
        $extractedData = $document->extracted_data ?? [];

        // Prioritize reviewed payload data over raw extraction
        $customerData = $payload['customer'] ?? ($extractedData['customer'] ?? []);
        $invoiceData = $payload['invoice'] ?? ($extractedData['invoice'] ?? []);
        $itemsData = $payload['items'] ?? ($extractedData['items'] ?? []);
        $totalsData = $payload['totals'] ?? ($extractedData['totals'] ?? []);
        $matchedCustomer = $payload['customer_match'] ?? ($extractedData['customer_match'] ?? null);

        // Handle Customer resolution
        $customerId = $matchedCustomer['id'] ?? null;

        // If user wants to create a new customer
        if (empty($customerId) && !empty($payload['create_new_customer']) && $payload['create_new_customer'] === true) {
            $newCustomer = Customer::create([
                'first_name' => $customerData['first_name'] ?: 'Customer',
                'last_name' => $customerData['last_name'] ?: '',
                'company_name' => $customerData['company_name'] ?: null,
                'email' => $customerData['email'] ?: 'contact@' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $customerData['company_name'] ?: 'company')) . '.com',
                'address_line1' => $customerData['address_line1'] ?: 'Standard Address',
                'address_line2' => $customerData['address_line2'] ?: null,
                'city' => $customerData['city'] ?: 'Bengaluru',
                'country' => $customerData['country'] ?: 'India',
                'payment_method' => $customerData['payment_method'] ?? 'Monthly Pay',
            ]);
            $customerId = $newCustomer->id;
        }

        // Format items for InvoiceService
        $formattedItems = [];
        foreach ($itemsData as $index => $item) {
            // Check if user specified create_new_item for unmatched items
            $itemId = $item['matched_item_id'] ?? null;
            if (empty($itemId) && !empty($item['create_as_new_item']) && $item['create_as_new_item'] === true) {
                $newItem = Item::create([
                    'item_name' => $item['item_name'],
                    'item_cost' => (float) ($item['unit_price'] ?? 0),
                    'gst_percentage' => (float) ($item['gst_percentage'] ?? 18),
                    'status' => 'active',
                ]);
                $itemId = $newItem->id;
            }

            $formattedItems[] = [
                'item_id' => $itemId,
                'item_name' => $item['item_name'] ?? 'Item ' . ($index + 1),
                'description' => $item['description'] ?? ($item['item_name'] ?? null),
                'quantity' => (float) ($item['quantity'] ?? 1),
                'price' => (float) ($item['unit_price'] ?? ($item['price'] ?? 0)),
                'gst_percentage' => (float) ($item['gst_percentage'] ?? 18),
                'sort_order' => $index,
            ];
        }

        $customerName = trim(($customerData['first_name'] ?? '') . ' ' . ($customerData['last_name'] ?? ''));
        if (empty($customerName)) {
            $customerName = $customerData['company_name'] ?? 'Client';
        }

        $invoicePayload = [
            'document_upload_id' => $document->id,
            'invoice_date' => $invoiceData['invoice_date'] ?? date('Y-m-d'),
            'due_date' => $invoiceData['due_date'] ?? date('Y-m-d', strtotime('+15 days')),
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'company_name' => $customerData['company_name'] ?? null,
            'customer_address' => trim(($customerData['address_line1'] ?? '') . ' ' . ($customerData['address_line2'] ?? '') . ' ' . ($customerData['city'] ?? '')),
            'customer_phone' => $customerData['phone'] ?? null,
            'customer_email' => $customerData['email'] ?? null,
            'payment_method' => $customerData['payment_method'] ?? 'Monthly Pay',
            'discount' => (float) ($totalsData['discount'] ?? 0),
            'additional_charges' => (float) ($totalsData['additional_charges'] ?? 0),
            'payment_terms' => $invoiceData['payment_terms'] ?? 'Net 15 days',
            'notes' => $invoiceData['notes'] ?? 'Auto-generated from uploaded document #' . $document->id,
            'status' => 'Draft', // Strict requirement: Set invoice status to DRAFT
            'items' => $formattedItems,
        ];

        try {
            $invoice = $this->invoiceService->createInvoice($invoicePayload);

            $document->update([
                'invoice_id' => $invoice->id,
                'status' => 'Draft Created',
            ]);

            $invoice->update(['document_upload_id' => $document->id]);

            return response()->json([
                'status' => 'success',
                'message' => "Draft Invoice #{$invoice->invoice_number} generated successfully.",
                'data' => [
                    'invoice' => $invoice,
                    'document' => $document->fresh(['invoice']),
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error("Failed to generate draft invoice: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate draft invoice: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Stream PDF for inline preview in the browser.
     */
    public function preview(DocumentUpload $document): BinaryFileResponse|JsonResponse
    {
        $fullPath = $this->getStorageFullPath($document->file_path);

        if (!file_exists($fullPath)) {
            return response()->json(['status' => 'error', 'message' => 'Document file not found.'], 404);
        }

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $document->original_name . '"',
        ]);
    }

    /**
     * Download the original uploaded PDF.
     */
    public function download(DocumentUpload $document): BinaryFileResponse|JsonResponse
    {
        $fullPath = $this->getStorageFullPath($document->file_path);

        if (!file_exists($fullPath)) {
            return response()->json(['status' => 'error', 'message' => 'Document file not found.'], 404);
        }

        return response()->download($fullPath, $document->original_name);
    }

    /**
     * Delete an uploaded document record and file.
     */
    public function destroy(DocumentUpload $document): JsonResponse
    {
        $fullPath = $this->getStorageFullPath($document->file_path);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        $document->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Document upload removed successfully.',
        ]);
    }

    /**
     * Helper to resolve the correct storage full path across Laravel local disk setups.
     */
    protected function getStorageFullPath(string $filePath): string
    {
        if (Storage::exists($filePath)) {
            return Storage::path($filePath);
        }
        $fallback = storage_path('app/' . $filePath);
        if (file_exists($fallback)) {
            return $fallback;
        }
        return Storage::path($filePath);
    }
}
