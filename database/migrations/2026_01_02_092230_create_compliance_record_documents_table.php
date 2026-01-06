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
        Schema::create('compliance_record_documents', function (Blueprint $table) {
            $table->id();

            // Many-to-many relationship
            $table->foreignId('compliance_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_document_id')->constrained()->onDelete('cascade');

            // Additional metadata
            $table->boolean('is_primary')->default(false); // Main evidence vs supporting

            $table->timestamps();

            // Prevent duplicate links
            $table->unique(['compliance_record_id', 'vehicle_document_id'], 'compliance_document_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_record_documents');
    }
};