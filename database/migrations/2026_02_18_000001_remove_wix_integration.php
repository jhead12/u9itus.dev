<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strategic Pivot: Remove all Wix integration columns and tables.
 * Transitioning to standalone Laravel 12 architecture.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Remove Wix columns from users table
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'wix_member_id')) {
                $table->dropColumn('wix_member_id');
            }
            if (Schema::hasColumn('users', 'wix_instance_id')) {
                $table->dropColumn('wix_instance_id');
            }
        });

        // Remove Wix columns from politicians table
        if (Schema::hasTable('politicians')) {
            Schema::table('politicians', function (Blueprint $table) {
                if (Schema::hasColumn('politicians', 'wix_site_id')) {
                    if (Schema::hasIndex('politicians', 'politicians_wix_site_id_foreign')) {
                        $table->dropForeign(['wix_site_id']);
                    }
                    $table->dropColumn('wix_site_id');
                }
                if (Schema::hasColumn('politicians', 'wix_member_id')) {
                    $table->dropColumn('wix_member_id');
                }
            });
        }

        // Remove Wix columns from voters table
        if (Schema::hasTable('voters')) {
            Schema::table('voters', function (Blueprint $table) {
                if (Schema::hasColumn('voters', 'wix_site_id')) {
                    if (Schema::hasIndex('voters', 'voters_wix_site_id_foreign')) {
                        $table->dropForeign(['wix_site_id']);
                    }
                    $table->dropColumn('wix_site_id');
                }
                if (Schema::hasColumn('voters', 'wix_member_id')) {
                    $table->dropColumn('wix_member_id');
                }
            });
        }

        // Drop the wix_sites table
        Schema::dropIfExists('wix_sites');
    }

    public function down(): void
    {
        // Re-create wix_sites table
        Schema::create('wix_sites', function (Blueprint $table) {
            $table->id();
            $table->string('instance_id')->unique();
            $table->string('site_display_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('app_installed')->default(false);
            $table->timestamps();
        });

        // Re-add Wix columns to users
        Schema::table('users', function (Blueprint $table) {
            $table->string('wix_member_id')->nullable()->after('is_verified');
            $table->string('wix_instance_id')->nullable()->after('wix_member_id');
        });

        // Re-add Wix columns to politicians
        if (Schema::hasTable('politicians')) {
            Schema::table('politicians', function (Blueprint $table) {
                $table->foreignId('wix_site_id')->nullable()->after('user_id')->constrained('wix_sites')->nullOnDelete();
                $table->string('wix_member_id')->nullable()->after('wix_site_id');
            });
        }

        // Re-add Wix columns to voters
        if (Schema::hasTable('voters')) {
            Schema::table('voters', function (Blueprint $table) {
                $table->foreignId('wix_site_id')->nullable()->after('user_id')->constrained('wix_sites')->nullOnDelete();
                $table->string('wix_member_id')->nullable()->after('wix_site_id');
            });
        }
    }
};
