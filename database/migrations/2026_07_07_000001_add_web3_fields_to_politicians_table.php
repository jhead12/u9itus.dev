<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 7 — MeToken Subgraph Read-Only Enrichment
 *
 * Adds two nullable Ethereum-address columns to the politicians table so the
 * Web3 transparency panel on /p/{slug} can look up on-chain meToken stats via
 * the public Goldsky subgraph.
 *
 * Both columns carry a UNIQUE constraint (nullable-safe on MySQL and Postgres)
 * to prevent ops copy-paste errors — two politicians should never share the
 * same wallet or meToken contract address.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->string('wallet_address', 42)
                ->nullable()
                ->after('claim_requested_at');
            $table->string('metoken_address', 42)
                ->nullable()
                ->after('wallet_address');

            $table->unique('wallet_address', 'politicians_wallet_address_unique');
            $table->unique('metoken_address', 'politicians_metoken_address_unique');
        });
    }

    public function down(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->dropUnique('politicians_wallet_address_unique');
            $table->dropUnique('politicians_metoken_address_unique');
            $table->dropColumn(['wallet_address', 'metoken_address']);
        });
    }
};
