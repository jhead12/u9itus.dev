<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE user_onboarding_progress MODIFY COLUMN user_type ENUM(
                'voter','politician','admin','citizen'
            ) NOT NULL");
        } else {
            // SQLite/others — enum is emulated via a CHECK constraint; switching to a
            // plain string drops that constraint (mirrors the users.user_type migration).
            Schema::table('user_onboarding_progress', function (Blueprint $table) {
                $table->string('user_type')->change();
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE user_onboarding_progress MODIFY COLUMN user_type ENUM(
                'voter','politician','admin'
            ) NOT NULL");
        } else {
            Schema::table('user_onboarding_progress', function (Blueprint $table) {
                $table->string('user_type')->change();
            });
        }
    }
};
