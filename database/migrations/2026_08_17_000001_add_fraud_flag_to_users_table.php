<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('flagged_for_fraud')->default(false)->after('registration_ip');
            $table->unsignedTinyInteger('fraud_score')->default(0)->after('flagged_for_fraud');
            $table->json('fraud_reasons')->nullable()->after('fraud_score');

            $table->index('flagged_for_fraud');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['flagged_for_fraud']);
            $table->dropColumn(['flagged_for_fraud', 'fraud_score', 'fraud_reasons']);
        });
    }
};
