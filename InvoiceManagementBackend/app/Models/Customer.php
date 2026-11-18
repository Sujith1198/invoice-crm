<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'company_name',
        'email',
        'address_line1',
        'address_line2',
        'city',
        'country',
        'payment_method',
        'status',
        'notes',
    ];

    protected $appends = ['full_name', 'primary_phone'];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getPrimaryPhoneAttribute(): ?string
    {
        $primary = $this->phones->firstWhere('is_primary', true) ?? $this->phones->first();
        return $primary ? $primary->phone_number : null;
    }

    public function phones(): HasMany
    {
        return $this->hasMany(CustomerPhone::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}

