<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen profile_photo_url from VARCHAR(255) to TEXT.
 *
 * Wikipedia image URLs (especially for historical figures with long filenames)
 * routinely exceed 255 characters, causing SQLSTATE[22001] truncation errors
 * during the politicians:backfill-photos command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->text('profile_photo_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            // Truncate any values that would no longer fit before reverting
            \Illuminate\Support\Facades\DB::statement(
                "UPDATE politicians SET profile_photo_url = LEFT(profile_photo_url, 255) WHERE CHAR_LENGTH(profile_photo_url) > 255"
            );
            $table->string('profile_photo_url')->nullable()->change();
        });
    }
};
