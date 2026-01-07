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
        Schema::table('compliance_audit_logs', function (Blueprint $table) {
            $table->json('old_values')->nullable()->change();
            $table->json('new_values')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compliance_audit_logs', function (Blueprint $table) {
            // We generally don't want to revert this as it might cause data loss or errors
            // if we try to make them required again but have nulls.
            // But strict reversal would be:
            // $table->json('old_values')->nullable(false)->change();
            // $table->json('new_values')->nullable(false)->change();
        });
    }
};
