<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * This migration performs three things:
 *
 * 1. Fixes the user_type enum to include 'user' (was missing, caused registration crashes).
 * 2. Makes first_name/last_name nullable (SSO users may lack them).
 * 3. Makes password nullable (SSO users authenticated via Wix have no password).
 * 4. Adds wix_member_id & wix_instance_id to users table for SSO linking.
 *
 * Note: SQLite doesn't support ALTER COLUMN for enums, so we use a workaround.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. For SQLite, drop the CHECK constraint and recreate it with new values
        // For other DBs, modify the enum - but Laravel's Schema builder handles this automatically
        Schema::table('users', function (Blueprint $table) {
            // Recreate user_type as TEXT (SQLite) or VARCHAR with new check constraint
            $table->string('user_type')->default('viewer')->change();
        });

        // 2. Make first_name and last_name nullable
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
        });

        // 3. Make password nullable for SSO users
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        // 4. Add Wix SSO identity columns
        Schema::table('users', function (Blueprint $table) {
            $table->string('wix_member_id')->nullable()->unique()->after('remember_token');
            $table->string('wix_instance_id')->nullable()->after('wix_member_id');

            $table->index('wix_instance_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['wix_instance_id']);
            $table->dropColumn(['wix_member_id', 'wix_instance_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
            $table->string('last_name')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('user_type')->default('advertiser')->change();
        });
    }
};
