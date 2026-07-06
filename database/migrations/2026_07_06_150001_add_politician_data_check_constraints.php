<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB-level guardrails mirroring App\Support\PoliticianDataRules.
 *
 * MySQL 8.0.16+ enforces CHECK constraints; each ADD is wrapped so the
 * migration is safe on:
 *  - older MySQL (constraint parsed but ignored — no failure),
 *  - sqlite (tests) — skipped entirely,
 *  - environments where existing rows still violate a rule (that ADD is
 *    skipped and logged; run politicians:audit-data-integrity --fix first,
 *    then re-run this migration to pick up the remaining constraints).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('politicians')) {
            return;
        }

        $constraints = [
            // Names must be at least 3 chars and never sentence-like artifacts.
            'chk_politicians_name_length' =>
                "ALTER TABLE politicians ADD CONSTRAINT chk_politicians_name_length CHECK (CHAR_LENGTH(TRIM(full_name)) >= 3)",
            'chk_politicians_name_not_artifact' =>
                "ALTER TABLE politicians ADD CONSTRAINT chk_politicians_name_not_artifact CHECK (
                    LOWER(full_name) NOT LIKE '%no incumbent%'
                    AND LOWER(full_name) NOT LIKE '%there are no%'
                    AND LOWER(full_name) NOT LIKE '%there were no%'
                    AND LOWER(full_name) NOT LIKE '% is %'
                    AND LOWER(full_name) NOT LIKE '%outcome%'
                    AND LOWER(full_name) NOT LIKE '%resign%'
                )",
            // State must be exactly 2 chars when set.
            'chk_politicians_state_format' =>
                "ALTER TABLE politicians ADD CONSTRAINT chk_politicians_state_format CHECK (state IS NULL OR CHAR_LENGTH(state) = 2)",
            // term_status whitelist when set.
            'chk_politicians_term_status' =>
                "ALTER TABLE politicians ADD CONSTRAINT chk_politicians_term_status CHECK (
                    term_status IS NULL OR term_status IN ('seated','active','running','lost','retired','former','eliminated')
                )",
        ];

        foreach ($constraints as $name => $sql) {
            try {
                DB::statement($sql);
            } catch (\Throwable $e) {
                // Existing rows violate the rule, or the constraint already
                // exists. Log and continue — the app-layer gate still protects
                // new writes; clean up with politicians:audit-data-integrity.
                logger()->warning("Skipped CHECK constraint {$name}: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('politicians')) {
            return;
        }

        foreach ([
            'chk_politicians_name_length',
            'chk_politicians_name_not_artifact',
            'chk_politicians_state_format',
            'chk_politicians_term_status',
        ] as $name) {
            try {
                DB::statement("ALTER TABLE politicians DROP CHECK {$name}");
            } catch (\Throwable) {
                // Constraint absent — nothing to drop.
            }
        }
    }
};
