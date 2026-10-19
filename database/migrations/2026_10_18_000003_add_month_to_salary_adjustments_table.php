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
        Schema::dropIfExists('salary_adjustments');

        Schema::create('salary_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month')->default(1);
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('hours_of_work', 6, 2)->default(0);
            $table->unsignedSmallInteger('days_of_work')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_adjustments');
    }
};
