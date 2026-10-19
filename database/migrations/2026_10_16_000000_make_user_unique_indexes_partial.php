<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let soft-deleted staff have their email / staff id reused by making the
     * unique indexes apply only to rows that are not soft-deleted.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_email_unique');
            DB::statement('DROP INDEX IF EXISTS users_employee_id_unique');
            DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX users_employee_id_unique ON users (employee_id) WHERE deleted_at IS NULL');

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropUnique(['employee_id']);
            $table->index('email');
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_email_unique');
            DB::statement('DROP INDEX IF EXISTS users_employee_id_unique');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['email']);
                $table->dropIndex(['employee_id']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
            $table->unique('employee_id');
        });
    }
};
