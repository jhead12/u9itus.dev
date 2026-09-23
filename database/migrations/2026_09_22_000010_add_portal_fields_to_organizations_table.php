<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * White-label portal fields for the client-branded voter guide product.
 * `org_type` stays the free string it already was (see config/organizations.php
 * for the controlled vocabulary enforced at the application layer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->json('portal_layout')->nullable()->after('description');
            $table->boolean('portal_published')->default(false)->after('portal_layout');
            $table->string('target_state', 2)->nullable()->after('portal_published');
            $table->string('target_district', 32)->nullable()->after('target_state');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['portal_layout', 'portal_published', 'target_state', 'target_district']);
        });
    }
};
