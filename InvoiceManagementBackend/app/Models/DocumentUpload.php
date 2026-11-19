<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentUpload extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'document_uploads';

    protected $fillable = [
        'file_name',
        'original_name',
        'file_path',
        'file_size',
        'file_hash',
        'mime_type',
        'status',
        'extracted_data',
        'error_message',
        'invoice_id',
        'user_id',
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'file_size' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
