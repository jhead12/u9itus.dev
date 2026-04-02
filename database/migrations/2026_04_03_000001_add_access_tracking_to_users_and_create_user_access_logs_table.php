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
            $table->string('last_seen_ip', 45)->nullable()->after('platform');
            $table->text('last_seen_user_agent')->nullable()->after('last_seen_ip');
            $table->boolean('last_seen_is_mobile')->default(false)->after('last_seen_user_agent');
            $table->boolean('last_seen_is_vpn_suspected')->nullable()->after('last_seen_is_mobile');
            $table->timestamp('last_seen_at')->nullable()->after('last_seen_is_vpn_suspected');
        });

        Schema::create('user_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32)->default('request')->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->boolean('is_mobile')->default(false)->index();
            $table->boolean('is_vpn_suspected')->nullable()->index();
            $table->string('vpn_signal', 100)->nullable();
            $table->string('request_path', 255)->nullable();
            $table->timestamp('accessed_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_access_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_seen_ip',
                'last_seen_user_agent',
                'last_seen_is_mobile',
                'last_seen_is_vpn_suspected',
                'last_seen_at',
            ]);
        });
    }
};
