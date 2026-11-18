<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('Orange Invoicing & CRM Ltd');
            $table->string('company_logo')->nullable();
            $table->text('company_address')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->string('gstin')->nullable();
            $table->string('invoice_prefix')->default('INV-');
            $table->string('default_currency')->default('INR');
            $table->string('default_currency_symbol')->default('₹');
            $table->text('default_payment_terms')->nullable();
            $table->text('invoice_footer')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->string('prefix', 20)->default('INV');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['year', 'prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('company_settings');
    }
};

