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
        Schema::table('vehicles', function (Blueprint $table) {
            // State of operation for compliance requirements
            $table->string('state_of_operation', 10)->nullable()->after('status');
            $table->foreign('state_of_operation')->references('code')->on('australian_states')->onDelete('set null');

            // Operational status (compliance-driven)
            $table->enum('operational_status', ['operational', 'non_operational', 'maintenance'])
                ->default('operational')
                ->after('state_of_operation');

            // Cached compliance score for performance (0-100)
            $table->decimal('compliance_score', 5, 2)->default(100.00)->after('operational_status');

            // Indexes
            $table->index('state_of_operation');
            $table->index('operational_status');
            $table->index('compliance_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['state_of_operation']);
            $table->dropIndex(['state_of_operation']);
            $table->dropIndex(['operational_status']);
            $table->dropIndex(['compliance_score']);
            $table->dropColumn(['state_of_operation', 'operational_status', 'compliance_score']);
        });
    }
};