<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizens', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('zip');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('business_category')->nullable()->after('business_name');
            // Opt-in: a business's precise address is self-asserted, high-exposure
            // content (matches the badge-visibility principle in
            // doc/BELONGING_MEANING_IDENTITY_STRATEGY.md) — never shown on the
            // map unless the citizen explicitly turns it on.
            $table->boolean('show_on_map')->default(false)->after('longitude');

            $table->index(['latitude', 'longitude']);
            $table->index('business_category');
        });
    }

    public function down(): void
    {
        Schema::table('citizens', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropIndex(['business_category']);
            $table->dropColumn(['latitude', 'longitude', 'business_category', 'show_on_map']);
        });
    }
};
