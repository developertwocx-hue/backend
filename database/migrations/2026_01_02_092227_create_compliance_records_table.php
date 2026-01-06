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
        Schema::create('compliance_records', function (Blueprint $table) {
            $table->id();

            // Tenant scoping
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');

            // Relationships
            $table->foreignId('vehicle_compliance_requirement_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade'); // Denormalized for performance
            $table->foreignId('compliance_type_id')->constrained()->onDelete('cascade'); // Denormalized for queries

            // Compliance Dates
            $table->date('issue_date');
            $table->date('expiry_date');

            // Inspection Details (for roadworthiness)
            $table->string('inspection_provider')->nullable();
            $table->string('inspection_number')->nullable();
            $table->text('notes')->nullable();

            // Approval Workflow
            $table->foreignId('submitted_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();

            // Current Record Tracking
            $table->boolean('is_current')->default(false); // Only one current record per requirement

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['vehicle_id', 'is_current']);
            $table->index(['compliance_type_id', 'expiry_date']);
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_records');
    }
};