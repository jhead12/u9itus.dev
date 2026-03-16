<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('district_lookup_searches', function (Blueprint $table) {
            $table->id();
            $table->string('query_address', 255);
            $table->string('matched_address', 255)->nullable();
            $table->string('state', 64)->nullable();
            $table->string('district_number', 64)->nullable();
            $table->string('district_code', 64)->nullable();
            $table->boolean('resolved')->default(false);
            $table->string('source', 64)->nullable();
            $table->string('error_message', 255)->nullable();
            $table->unsignedInteger('discovered_officials_count')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['state', 'district_code']);
            $table->index('resolved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district_lookup_searches');
    }
};
