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
        // Remove ALL fields from vehicles table except essentials
        // Vehicle data will come ONLY from vehicle_type_fields
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'make',
                'model',
                'year',
                'registration_number',
                'vin',
                'serial_number',
                'purchase_date',
                'purchase_price',
                'last_service_date',
                'next_service_date',
                'notes',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('vin')->nullable()->unique();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->date('last_service_date')->nullable();
            $table->date('next_service_date')->nullable();
            $table->text('notes')->nullable();
        });
    }
};
