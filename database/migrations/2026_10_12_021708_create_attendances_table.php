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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date')->index();
            $table->timestamp('clock_in_at')->nullable();
            $table->string('clock_in_photo_path')->nullable();
            $table->decimal('clock_in_face_score', 5, 4)->nullable();
            $table->string('clock_in_ip', 45)->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->string('clock_out_photo_path')->nullable();
            $table->decimal('clock_out_face_score', 5, 4)->nullable();
            $table->string('clock_out_ip', 45)->nullable();
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
