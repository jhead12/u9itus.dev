<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add Phase 13 columns to politicians ────────────────────────
        Schema::table('politicians', function (Blueprint $table) {
            // slug format: {5-char-uuid-prefix}-{seo-readable-name}
            // e.g. a3f9b-mayor-john-smith-chicago
            $table->string('slug')->nullable()->unique()->after('uuid');

            // JSON object: layout_preset, primary_color, accent_color,
            // background_style, hero_banner_url, section toggles, custom CTA
            $table->json('page_settings')->nullable()->after('bio');

            // Whether the public profile page is visible
            $table->boolean('page_published')->default(false)->after('page_settings');
        });

        // Backfill slugs for any existing politicians using uuid prefix + name
        DB::table('politicians')->orderBy('id')->each(function (object $row) {
            $prefix  = substr($row->uuid, 0, 5);
            $office  = $row->political_office ?? 'official';
            $city    = $row->city ?? '';
            $base    = Str::slug("{$office} {$row->full_name} {$city}");
            $slug    = "{$prefix}-{$base}";
            $counter = 0;
            while (DB::table('politicians')->where('slug', $slug)->where('id', '!=', $row->id)->exists()) {
                $counter++;
                $slug = "{$prefix}-{$base}-{$counter}";
            }
            DB::table('politicians')->where('id', $row->id)->update(['slug' => $slug]);
        });

        // Now enforce NOT NULL after backfill
        Schema::table('politicians', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });

        // ── 2. politician_pages ───────────────────────────────────────────
        Schema::create('politician_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('politician_id')->unique()->constrained()->cascadeOnDelete();

            // Layout & theme
            $table->enum('layout_preset', ['classic', 'modern', 'bold', 'minimal'])->default('classic');
            $table->string('primary_color', 7)->default('#1e40af');   // hex
            $table->string('accent_color',  7)->default('#f59e0b');   // hex
            $table->enum('background_style', ['dark', 'light', 'gradient', 'image'])->default('dark');
            $table->string('hero_banner_url')->nullable();

            // Section visibility toggles
            $table->boolean('show_bio')->default(true);
            $table->boolean('show_initiatives')->default(true);
            $table->boolean('show_campaigns')->default(true);
            $table->boolean('show_contact')->default(true);

            // Custom call-to-action
            $table->string('custom_cta_text')->nullable();
            $table->string('custom_cta_url')->nullable();

            $table->timestamps();
        });

        // ── 3. politician_initiatives ─────────────────────────────────────
        Schema::create('politician_initiatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('politician_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // icon: emoji or heroicon name (e.g. "🏥" or "heart")
            $table->string('icon', 64)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['politician_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politician_initiatives');
        Schema::dropIfExists('politician_pages');

        Schema::table('politicians', function (Blueprint $table) {
            $table->dropColumn(['slug', 'page_settings', 'page_published']);
        });
    }
};
