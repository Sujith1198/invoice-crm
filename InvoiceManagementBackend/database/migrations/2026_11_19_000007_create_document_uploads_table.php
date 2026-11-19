<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('original_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_hash', 64)->index();
            $table->string('mime_type')->default('application/pdf');
            $table->enum('status', [
                'Uploaded',
                'Reading PDF',
                'Extracting Information',
                'Review Required',
                'Draft Created',
                'Failed'
            ])->default('Uploaded')->index();
            $table->json('extracted_data')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Add document_upload_id foreign key to invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('document_upload_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('document_uploads')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['document_upload_id']);
            $table->dropColumn('document_upload_id');
        });

        Schema::dropIfExists('document_uploads');
    }
};
