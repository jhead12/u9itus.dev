<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('politicians')) {
            Schema::table('politicians', function (Blueprint $table) {
                if (! Schema::hasColumn('politicians', 'profile_photo_status')) {
                    $table->string('profile_photo_status', 24)->default('unvalidated')->after('profile_photo_url');
                }
                if (! Schema::hasColumn('politicians', 'profile_photo_validation_confidence')) {
                    $table->decimal('profile_photo_validation_confidence', 4, 3)->nullable()->after('profile_photo_status');
                }
                if (! Schema::hasColumn('politicians', 'profile_photo_last_validated_at')) {
                    $table->timestamp('profile_photo_last_validated_at')->nullable()->after('profile_photo_validation_confidence');
                }
            });
        }

        if (! Schema::hasTable('politician_photo_quarantines')) {
            Schema::create('politician_photo_quarantines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('politician_id')->constrained('politicians')->cascadeOnDelete();
                $table->string('photo_url', 2048);
                $table->char('photo_url_hash', 64);
                $table->string('status', 24)->default('pending'); // pending|approved|rejected|auto_cleared
                $table->string('validator', 32)->nullable(); // heuristic|anthropic
                $table->decimal('confidence', 4, 3)->nullable();
                $table->string('reason', 255)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->unique(['politician_id', 'photo_url_hash'], 'politician_photo_quarantines_unique_photo_hash');
            });
        } else {
            Schema::table('politician_photo_quarantines', function (Blueprint $table) {
                if (! Schema::hasColumn('politician_photo_quarantines', 'photo_url_hash')) {
                    $table->char('photo_url_hash', 64)->nullable()->after('photo_url');
                }
            });

            DB::table('politician_photo_quarantines')
                ->where(function ($q) {
                    $q->whereNull('photo_url_hash')->orWhere('photo_url_hash', '');
                })
                ->orderBy('id')
                ->select(['id', 'photo_url'])
                ->chunkById(250, function ($rows) {
                    foreach ($rows as $row) {
                        $hash = hash('sha256', strtolower(trim((string) $row->photo_url)));
                        DB::table('politician_photo_quarantines')
                            ->where('id', $row->id)
                            ->update(['photo_url_hash' => $hash]);
                    }
                });

            try {
                DB::statement('ALTER TABLE politician_photo_quarantines MODIFY photo_url_hash CHAR(64) NOT NULL');
            } catch (\Throwable) {
                // Ignore when engine/version already has compatible definition.
            }

            try {
                DB::statement('CREATE UNIQUE INDEX politician_photo_quarantines_unique_photo_hash ON politician_photo_quarantines (politician_id, photo_url_hash)');
            } catch (\Throwable) {
                // Index already exists or cannot be created on this engine/version.
            }
        }

        if (Schema::hasTable('candidate_news_articles')) {
            Schema::table('candidate_news_articles', function (Blueprint $table) {
                if (! Schema::hasColumn('candidate_news_articles', 'verification_status')) {
                    $table->string('verification_status', 24)->default('verified')->after('provider'); // pending|verified|rejected
                }
                if (! Schema::hasColumn('candidate_news_articles', 'verification_reason')) {
                    $table->string('verification_reason', 255)->nullable()->after('verification_status');
                }
                if (! Schema::hasColumn('candidate_news_articles', 'verification_confidence')) {
                    $table->decimal('verification_confidence', 4, 3)->nullable()->after('verification_reason');
                }
                if (! Schema::hasColumn('candidate_news_articles', 'name_match_score')) {
                    $table->decimal('name_match_score', 4, 3)->nullable()->after('verification_confidence');
                }
                if (! Schema::hasColumn('candidate_news_articles', 'context_match_score')) {
                    $table->decimal('context_match_score', 4, 3)->nullable()->after('name_match_score');
                }
                if (! Schema::hasColumn('candidate_news_articles', 'topic_key')) {
                    $table->string('topic_key', 100)->nullable()->after('context_match_score');
                }
                if (! Schema::hasColumn('candidate_news_articles', 'topic_confidence')) {
                    $table->decimal('topic_confidence', 4, 3)->nullable()->after('topic_key');
                }
                if (! Schema::hasColumn('candidate_news_articles', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('topic_confidence');
                }
                if (! Schema::hasColumn('candidate_news_articles', 'verification_meta')) {
                    $table->json('verification_meta')->nullable()->after('verified_at');
                }
            });

            // Index creation is wrapped for partial-deploy safety.
            try {
                DB::statement('CREATE INDEX candidate_news_articles_verified_idx ON candidate_news_articles (politician_id, verification_status, published_at)');
            } catch (\Throwable) {
                // Index already exists or cannot be created on this engine/version.
            }
            try {
                DB::statement('CREATE INDEX candidate_news_articles_status_idx ON candidate_news_articles (verification_status, published_at)');
            } catch (\Throwable) {
                // Index already exists or cannot be created on this engine/version.
            }
            try {
                DB::statement('CREATE INDEX candidate_news_articles_topic_idx ON candidate_news_articles (topic_key, published_at)');
            } catch (\Throwable) {
                // Index already exists or cannot be created on this engine/version.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('candidate_news_articles')) {
            try {
                DB::statement('DROP INDEX candidate_news_articles_verified_idx ON candidate_news_articles');
            } catch (\Throwable) {
                // Ignore when index does not exist.
            }
            try {
                DB::statement('DROP INDEX candidate_news_articles_status_idx ON candidate_news_articles');
            } catch (\Throwable) {
                // Ignore when index does not exist.
            }
            try {
                DB::statement('DROP INDEX candidate_news_articles_topic_idx ON candidate_news_articles');
            } catch (\Throwable) {
                // Ignore when index does not exist.
            }

            $newsCols = [
                'verification_status',
                'verification_reason',
                'verification_confidence',
                'name_match_score',
                'context_match_score',
                'topic_key',
                'topic_confidence',
                'verified_at',
                'verification_meta',
            ];

            foreach ($newsCols as $col) {
                if (Schema::hasColumn('candidate_news_articles', $col)) {
                    Schema::table('candidate_news_articles', function (Blueprint $table) use ($col) {
                        $table->dropColumn($col);
                    });
                }
            }
        }

        Schema::dropIfExists('politician_photo_quarantines');

        if (Schema::hasTable('politicians')) {
            $photoCols = [
                'profile_photo_status',
                'profile_photo_validation_confidence',
                'profile_photo_last_validated_at',
            ];

            foreach ($photoCols as $col) {
                if (Schema::hasColumn('politicians', $col)) {
                    Schema::table('politicians', function (Blueprint $table) use ($col) {
                        $table->dropColumn($col);
                    });
                }
            }
        }
    }
};
