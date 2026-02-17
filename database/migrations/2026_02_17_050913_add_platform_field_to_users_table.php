<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add platform field to users table for dual-platform support.
 * 
 * This allows us to track which platform a user registered from:
 * - 'wix' for Wix App Extension users
 * - 'standalone' for standalone application users
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('platform', ['wix', 'standalone'])
                ->default('standalone')
                ->after('wix_instance_id')
                ->comment('Platform the user registered from');
            
            // Add index for faster platform-based queries
            $table->index('platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['platform']);
            $table->dropColumn('platform');
        });
    }
};

