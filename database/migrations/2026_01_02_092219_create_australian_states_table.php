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
        Schema::create('australian_states', function (Blueprint $table) {
            $table->string('code', 10)->primary(); // NSW, VIC, QLD, etc.
            $table->string('name'); // New South Wales, Victoria, etc.
            $table->boolean('has_green_sticker_requirement')->default(false);
            $table->integer('green_sticker_frequency_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('australian_states');
    }
};