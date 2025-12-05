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
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name');
            $table->text('description')->nullable();

            // Scope Definitions
            // NULL = applies globally, NOT NULL = applies to specific vehicle type
            $table->foreignId('vehicle_type_id')->nullable()->constrained('vehicle_types')->onDelete('cascade');

            // NULL = created by superadmin, NOT NULL = created by tenant
            $table->string('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');

            // Settings
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(100);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient querying
            $table->index(['vehicle_type_id', 'tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
