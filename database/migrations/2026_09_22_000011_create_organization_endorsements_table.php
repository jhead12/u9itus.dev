<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client organization's own curated endorsement/position badge for its
 * white-label portal — e.g. "Endorsed by Our Union" or "Vote YES on Prop 1".
 *
 * Deliberately NOT the same table as politician_endorsements, which is
 * coupled to App\Services\EndorsementClassifier's news-article detection
 * (source_article_id, matched_phrase, confidence, detected_article_ids) and
 * documented as automated-only. This table holds manually-entered,
 * client-attributed positions instead.
 *
 * `politician_id` is only ever set for organizations whose org_type allows
 * candidate endorsements (see config/organizations.php + validation in
 * App\Http\Requests\StoreOrganizationEndorsementRequest) — a 501(c)(3)
 * nonprofit may only endorse ballot measures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_endorsements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('politician_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ballot_measure_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('position', ['endorse', 'oppose', 'neutral'])->default('endorse');
            $table->string('label', 128);
            $table->text('note')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_endorsements');
    }
};
