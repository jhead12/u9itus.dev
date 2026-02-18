<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // For MySQL: ALTER the enum to include voter and politician values.
        // For SQLite (dev/test): the column is already a string, so just update
        // the default. SQLite ignores ENUM, it stores as TEXT.
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM(
                'advertiser','viewer','admin','voter','politician'
            ) NOT NULL DEFAULT 'voter'");
        } else {
            // SQLite — column is already a plain string, just change the default
            Schema::table('users', function (Blueprint $table) {
                $table->string('user_type')->default('voter')->change();
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM(
                'advertiser','viewer','admin'
            ) NOT NULL DEFAULT 'viewer'");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('user_type')->default('viewer')->change();
            });
        }
    }
};
