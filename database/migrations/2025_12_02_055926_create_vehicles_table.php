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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');

            $table->foreignId('vehicle_type_id')->constrained('vehicle_types')->onDelete('restrict');

            // Vehicle Basic Information
            $table->string('name');
            $table->string('make')->nullable(); // manufacturer
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('vin')->nullable(); // Vehicle Identification Number
            $table->string('serial_number')->nullable();

            // Specifications
            $table->decimal('capacity', 10, 2)->nullable(); // lifting capacity
            $table->string('capacity_unit')->default('tons');
            $table->text('specifications')->nullable();

            // Status and Maintenance
            $table->enum('status', ['active', 'maintenance', 'inactive', 'sold'])->default('active');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->date('last_service_date')->nullable();
            $table->date('next_service_date')->nullable();

            // Additional Info
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
