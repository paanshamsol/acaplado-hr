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
        Schema::create('company_operational_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_settings_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_operational_times');
    }
};
