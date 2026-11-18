<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'item_name',
        'import_declaration',
        'export_declaration',
        'item_cost',
        'gst_percentage',
        'certificate_of_origin',
        'xo_permit',
        'amendment',
        'status',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'item_cost' => 'decimal:2',
        'gst_percentage' => 'decimal:2',
    ];

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}

