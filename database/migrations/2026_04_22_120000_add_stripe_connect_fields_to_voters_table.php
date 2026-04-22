<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('cashapp_tag');
            $table->string('stripe_account_status', 32)->default('pending')->after('stripe_account_id');

            $table->index('stripe_account_id', 'voters_stripe_account_id_idx');
            $table->index('stripe_account_status', 'voters_stripe_account_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropIndex('voters_stripe_account_id_idx');
            $table->dropIndex('voters_stripe_account_status_idx');
            $table->dropColumn([
                'stripe_account_id',
                'stripe_account_status',
            ]);
        });
    }
};
