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
        // Create vehicle_type_fields table
        // This single table holds BOTH default fields (tenant_id = NULL) and custom fields (tenant_id = tenant's id)
        Schema::create('vehicle_type_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_type_id')->constrained()->onDelete('cascade');
            $table->string('tenant_id')->nullable()->index(); // NULL = default field, NOT NULL = custom field
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('name'); // Display name: "Bed Capacity", "GPS Tracker ID"
            $table->string('key'); // Machine key: "bed_capacity", "gps_tracker_id"
            $table->enum('field_type', ['text', 'number', 'date', 'select', 'boolean', 'textarea'])->default('text');
            $table->string('unit')->nullable(); // "tons", "meters", etc.
            $table->json('options')->nullable(); // For select fields
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();

            $table->timestamps();

            // Unique constraint: vehicle_type + tenant + key
            $table->unique(['vehicle_type_id', 'tenant_id', 'key'], 'vehicle_type_tenant_key_unique');

            // Index for performance
            $table->index(['vehicle_type_id', 'tenant_id', 'is_active', 'sort_order'], 'vehicle_type_fields_lookup');
        });

        // Create vehicle_field_values table
        // Stores actual values for both default and custom fields
        Schema::create('vehicle_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_type_field_id')->constrained()->onDelete('cascade');
            $table->text('value')->nullable();
            $table->timestamps();

            // Unique constraint: one value per vehicle per field
            $table->unique(['vehicle_id', 'vehicle_type_field_id'], 'vehicle_field_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_field_values');
        Schema::dropIfExists('vehicle_type_fields');
    }
};
