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
        Schema::create('vehicle_compliance_requirements', function (Blueprint $table) {
            $table->id();

            // Tenant scoping
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');

            // Relationships
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('compliance_type_id')->constrained()->onDelete('cascade');

            // Overrides
            $table->boolean('is_required')->default(true);
            $table->string('state_override', 10)->nullable(); // Override state if vehicle operates across states

            $table->timestamps();

            // Unique constraint: one requirement per vehicle per compliance type
            $table->unique(['vehicle_id', 'compliance_type_id'], 'vehicle_compliance_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_compliance_requirements');
    }
};