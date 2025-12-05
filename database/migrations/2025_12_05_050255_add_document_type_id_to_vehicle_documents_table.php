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
        Schema::table('vehicle_documents', function (Blueprint $table) {
            // Add document_type_id foreign key
            $table->foreignId('document_type_id')->nullable()->after('vehicle_id')->constrained('document_types')->onDelete('set null');

            // Keep old document_type field for backward compatibility during transition
            // Will be removed in future migration after data migration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->dropForeign(['document_type_id']);
            $table->dropColumn('document_type_id');
        });
    }
};
