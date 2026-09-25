<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CongressFloorSpeech extends Model
{
    protected $fillable = [
        'granule_id', 'bioguide_id', 'chamber', 'kind', 'spoken_on', 'record_time', 'title', 'record_section',
        'citation', 'body', 'word_count', 'source_url', 'topic_key', 'topic_confidence', 'stance', 'topic_stance',
        'position_summary', 'quote', 'analysis_method', 'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'spoken_on' => 'date',
            'word_count' => 'integer',
            'topic_confidence' => 'float',
            'analyzed_at' => 'datetime',
        ];
    }

    public function topic()
    {
        return $this->belongsTo(PoliticianTopic::class, 'topic_key', 'slug');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('body', 'like', $like));
    }

    /**
     * C-SPAN's Congressional Chronicle page for that chamber and day, where the floor
     * video plays alongside the Record. Written Extensions of Remarks were never spoken.
     */
    public function cspanUrl(): ?string
    {
        if ($this->kind !== 'floor') {
            return null;
        }

        return 'https://www.c-span.org/congress/?'.http_build_query(['chamber' => $this->chamber, 'date' => $this->spoken_on->toDateString()]);
    }

    private const MINOR_WORDS = ['a', 'an', 'and', 'as', 'at', 'by', 'for', 'from', 'in', 'of', 'on', 'or', 'the', 'to', 'with'];

    /**
     * Title case for the Record's all-caps headings, keeping abbreviations such as
     * "H.R." and "U.S." intact and short connecting words lowercase.
     */
    public function displayTitle(): string
    {
        $words = explode(' ', preg_replace('/\s+/', ' ', trim($this->title)));

        return implode(' ', array_map(function (string $word, int $i) {
            if (preg_match('/^(?:[A-Z]\.){2,}$/', $word)) {
                return $word;
            }
            $lower = mb_strtolower($word);

            return $i > 0 && in_array($lower, self::MINOR_WORDS, true) ? $lower : mb_convert_case($lower, MB_CASE_TITLE);
        }, $words, array_keys($words)));
    }
}
