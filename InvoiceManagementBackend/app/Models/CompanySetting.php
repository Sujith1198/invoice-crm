<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'company_logo',
        'company_address',
        'phone_number',
        'email',
        'gstin',
        'invoice_prefix',
        'default_currency',
        'default_currency_symbol',
        'default_payment_terms',
        'invoice_footer',
        'terms_and_conditions',
    ];
}

