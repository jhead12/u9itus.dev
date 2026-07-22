<?php

namespace App\Enums;

/**
 * The kind of source material a marketing draft was generated from.
 *
 * Stored on marketing_post_drafts.source_type and folded into the dedup
 * source_hash (`{politician_id}|{source_type}|{source_id}`). Mirrors the
 * enricher house style of lowercase-string enum values persisted to a string
 * column.
 */
enum MarketingSourceType: string
{
    case News        = 'news';        // CandidateNewsArticle
    case ViralMoment = 'viral_moment'; // PoliticianViralMoment
}