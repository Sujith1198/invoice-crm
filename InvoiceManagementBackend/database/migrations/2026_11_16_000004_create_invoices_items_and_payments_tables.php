<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->date('invoice_date')->index();
            $table->date('due_date')->index();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Snapshot fields (editable per invoice without mutating the original customer)
            $table->string('customer_name');
            $table->string('company_name')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('payment_method')->nullable();

            // Financial amounts
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('total_gst', 15, 2)->default(0.00);
            $table->decimal('discount', 15, 2)->default(0.00);
            $table->decimal('additional_charges', 15, 2)->default(0.00);
            $table->decimal('grand_total', 15, 2)->default(0.00);
            $table->decimal('amount_paid', 15, 2)->default(0.00);
            $table->decimal('balance_due', 15, 2)->default(0.00);

            // Notes and status
            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->enum('status', ['Draft', 'Generated', 'Partially Paid', 'Paid', 'Overdue', 'Cancelled'])
                  ->default('Draft')
                  ->index();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('item_name');
            $table->text('description')->nullable(); // Proceed / Description
            $table->decimal('price', 15, 2)->default(0.00);
            $table->decimal('quantity', 15, 2)->default(1.00);
            $table->decimal('gst_percentage', 5, 2)->default(0.00);
            $table->decimal('gst_amount', 15, 2)->default(0.00);
            $table->decimal('line_total', 15, 2)->default(0.00);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('payment_number')->unique();
            $table->date('payment_date')->index();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method'); // Bank Transfer, UPI, Cash, Cheque, Credit Card
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};

