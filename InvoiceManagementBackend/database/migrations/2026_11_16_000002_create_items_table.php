<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('item_name')->index();
            $table->text('import_declaration')->nullable();
            $table->text('export_declaration')->nullable();
            $table->decimal('item_cost', 15, 2)->default(0.00);
            $table->decimal('gst_percentage', 5, 2)->default(18.00);
            $table->string('certificate_of_origin')->nullable();
            $table->string('xo_permit')->nullable();
            $table->string('amendment')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

