<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_upload_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'customer_id',
        'customer_name',
        'company_name',
        'customer_address',
        'customer_phone',
        'customer_email',
        'payment_method',
        'subtotal',
        'total_gst',
        'discount',
        'additional_charges',
        'grand_total',
        'amount_paid',
        'balance_due',
        'payment_terms',
        'notes',
        'terms_and_conditions',
        'status',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'total_gst' => 'decimal:2',
        'discount' => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function documentUpload(): BelongsTo
    {
        return $this->belongsTo(DocumentUpload::class, 'document_upload_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order', 'asc');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('payment_date', 'desc');
    }
}

