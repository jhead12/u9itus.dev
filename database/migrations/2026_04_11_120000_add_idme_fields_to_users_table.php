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
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'idme_uuid')) {
                $table->string('idme_uuid')->nullable()->after('kyc_document_path');
                $table->index('idme_uuid');
            }

            if (! Schema::hasColumn('users', 'idme_verified_at')) {
                $table->timestamp('idme_verified_at')->nullable()->after('idme_uuid');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'idme_verified_at')) {
                $table->dropColumn('idme_verified_at');
            }

            if (Schema::hasColumn('users', 'idme_uuid')) {
                $table->dropIndex(['idme_uuid']);
                $table->dropColumn('idme_uuid');
            }
        });
    }
};
