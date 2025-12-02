<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');

            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');

            // Document Information
            $table->string('document_type'); // registration, insurance, inspection, manual, etc.
            $table->string('document_name');
            $table->string('document_number')->nullable();
            $table->string('file_path'); // storage path
            $table->string('file_type')->nullable(); // pdf, image, etc.
            $table->bigInteger('file_size')->nullable(); // in bytes

            // Validity
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_expired')->default(false);

            // Additional Info
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_documents');
    }
};
