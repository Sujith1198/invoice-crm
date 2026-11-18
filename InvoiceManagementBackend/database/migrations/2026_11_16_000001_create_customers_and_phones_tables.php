<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('company_name')->nullable();
            $table->string('email')->index();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city')->index();
            $table->string('country')->default('India');
            $table->enum('payment_method', ['Daily Pay', 'Monthly Pay', '15 Days Pay'])->default('Monthly Pay')->index();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('phone_number');
            $table->boolean('is_primary')->default(false);
            $table->string('type')->default('Mobile'); // Mobile, Work, Home, Alternate
            $table->timestamps();

            $table->index(['customer_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_phones');
        Schema::dropIfExists('customers');
    }
};

