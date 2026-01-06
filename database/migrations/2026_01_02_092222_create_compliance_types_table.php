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
        Schema::create('compliance_types', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['roadworthiness', 'registration', 'insurance', 'inspection', 'other'])->default('other');

            // Scope Definitions (similar to DocumentType pattern)
            // NULL = global, NOT NULL = specific
            $table->foreignId('vehicle_type_id')->nullable()->constrained('vehicle_types')->onDelete('cascade');
            $table->string('state_code', 10)->nullable();
            $table->foreign('state_code')->references('code')->on('australian_states')->onDelete('cascade');
            $table->string('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Compliance Rules
            $table->integer('renewal_frequency_days')->nullable(); // Auto-renewal interval
            $table->boolean('requires_document')->default(true); // Must upload evidence
            $table->json('accepted_document_types')->nullable(); // Array of document_type IDs
            $table->boolean('is_required')->default(false); // Mandatory vs optional
            $table->boolean('is_active')->default(true);

            // Alert Configuration
            $table->json('alert_thresholds')->nullable(); // [30, 14, 7, 0] days before expiry

            // Sorting
            $table->integer('sort_order')->default(100);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient querying
            $table->index(['vehicle_type_id', 'state_code', 'tenant_id', 'is_active']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_types');
    }
};