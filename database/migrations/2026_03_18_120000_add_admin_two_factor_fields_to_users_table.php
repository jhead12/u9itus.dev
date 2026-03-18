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
            $table->text('admin_two_factor_secret')->nullable()->after('remember_token');
            $table->timestamp('admin_two_factor_confirmed_at')->nullable()->after('admin_two_factor_secret');
            $table->text('admin_two_factor_recovery_codes')->nullable()->after('admin_two_factor_confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'admin_two_factor_secret',
                'admin_two_factor_confirmed_at',
                'admin_two_factor_recovery_codes',
            ]);
        });
    }
};
