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
        Schema::create('compliance_alerts', function (Blueprint $table) {
            $table->id();

            // Tenant scoping
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');

            // Relationships
            $table->foreignId('compliance_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');

            // Alert Details
            $table->enum('alert_type', ['expiring_30', 'expiring_14', 'expiring_7', 'expired', 'escalation']);
            $table->integer('days_until_expiry');

            // Alert Status
            $table->enum('status', ['pending', 'sent', 'failed', 'acknowledged'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->json('sent_to')->nullable(); // [{user_id, email, channel}]

            // Acknowledgement
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('acknowledged_at')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['compliance_record_id', 'alert_type']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_alerts');
    }
};