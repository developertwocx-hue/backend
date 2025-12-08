<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('qr_code_token', 64)->unique()->nullable()->after('status');
        });

        // Generate QR tokens for existing vehicles
        $vehicles = DB::table('vehicles')->whereNull('qr_code_token')->get();
        foreach ($vehicles as $vehicle) {
            DB::table('vehicles')
                ->where('id', $vehicle->id)
                ->update(['qr_code_token' => Str::random(32)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('qr_code_token');
        });
    }
};
