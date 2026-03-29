<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE political_campaigns MODIFY media_type ENUM('youtube','vimeo','direct_file','s3_cloudfront','hls_stream') NOT NULL DEFAULT 'youtube'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE political_campaigns MODIFY media_type ENUM('youtube','vimeo','direct_file','s3_cloudfront') NOT NULL DEFAULT 'youtube'");
    }
};
