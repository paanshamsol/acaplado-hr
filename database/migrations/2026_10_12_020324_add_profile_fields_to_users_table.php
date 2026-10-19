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
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->nullable()->unique()->after('id');
            $table->string('phone')->nullable()->after('email');
            $table->string('profile_photo_path')->nullable();
            $table->json('face_descriptor')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->date('join_date')->nullable();
            $table->string('pay_basis')->default('monthly');
            $table->decimal('monthly_salary', 10, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'employee_id',
                'phone',
                'profile_photo_path',
                'face_descriptor',
                'department',
                'position',
                'join_date',
                'pay_basis',
                'monthly_salary',
                'hourly_rate',
                'status',
            ]);
        });
    }
};
