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
        Schema::create('compliance_audit_logs', function (Blueprint $table) {
            $table->id();

            // Tenant scoping
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');

            // Related records
            $table->foreignId('compliance_record_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Action details
            $table->enum('action', [
                'created',
                'updated',
                'deleted',
                'document_uploaded',
                'document_removed',
                'approved',
                'rejected',
                'status_changed'
            ]);

            // Change tracking
            $table->json('old_values')->nullable();
            $table->json('new_values');

            // Request metadata
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Immutable - no updated_at
            $table->timestamp('created_at')->useCurrent();

            // Indexes for auditing
            $table->index(['vehicle_id', 'created_at']);
            $table->index(['compliance_record_id', 'action']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_audit_logs');
    }
};