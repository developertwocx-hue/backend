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
        Schema::table('compliance_records', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('notes');
            $table->string('document_name')->nullable()->after('document_path');
            $table->string('document_type')->nullable()->after('document_name'); // e.g. application/pdf
            $table->bigInteger('document_size')->nullable()->after('document_type'); // in bytes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compliance_records', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'document_name', 'document_type', 'document_size']);
        });
    }
};
