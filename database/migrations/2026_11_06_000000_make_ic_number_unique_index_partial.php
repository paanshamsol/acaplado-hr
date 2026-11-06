<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let a soft-deleted staff member's IC number be reused.
     *
     * `2026_10_18_add_address_and_ic_number_to_users_table` added a plain
     * unique index on `ic_number`. Validation ignores soft-deleted staff, so
     * reusing the IC of a deleted staff member passed validation and then
     * failed at the database with a 1062 duplicate entry error (HTTP 500).
     * Align it with the email / employee_id indexes from
     * `2026_10_16_000000_make_user_unique_indexes_partial`.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_ic_number_unique');
            DB::statement('CREATE UNIQUE INDEX users_ic_number_unique ON users (ic_number) WHERE deleted_at IS NULL');

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ic_number']);
            $table->index('ic_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_ic_number_unique');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['ic_number']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('ic_number');
        });
    }
};
