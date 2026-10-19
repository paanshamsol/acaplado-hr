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
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('hours_of_work', 6, 2)->default(0);
            $table->unsignedSmallInteger('days_of_work')->default(0);
            $table->decimal('basic', 12, 2)->default(0);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('epf_employee', 12, 2)->default(0);
            $table->decimal('epf_employer', 12, 2)->default(0);
            $table->decimal('socso_employee', 12, 2)->default(0);
            $table->decimal('socso_employer', 12, 2)->default(0);
            $table->decimal('eis_employee', 12, 2)->default(0);
            $table->decimal('eis_employer', 12, 2)->default(0);
            $table->decimal('pcb', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->string('status')->default('draft');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
