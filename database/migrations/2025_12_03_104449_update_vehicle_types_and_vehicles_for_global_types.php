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
        // Make vehicle_types global (remove tenant_id constraint)
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        // Remove redundant fields from vehicles table since we now use vehicle_type_fields
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'capacity',
                'capacity_unit',
                'specifications'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back tenant_id to vehicle_types
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Add back removed fields to vehicles
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('capacity', 10, 2)->nullable()->after('serial_number');
            $table->string('capacity_unit')->nullable()->after('capacity');
            $table->text('specifications')->nullable()->after('capacity_unit');
        });
    }
};
