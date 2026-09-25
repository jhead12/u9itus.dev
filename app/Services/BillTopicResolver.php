<?php

namespace App\Services;

use App\Models\CongressBill;
use App\Models\CongressVote;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The topic of a floor speech from the bill it is about, so a debate on a bill is filed
 * under what the bill does rather than what a classifier reads into the remarks.
 *
 * From the bills GovInfo tags as the subject of the speech:
 *   1. vote — an editor tied a roll call on one of them to a topic (congress_vote_topics);
 *   2. bill — each bill's Congress.gov title keywords, else its policy area, used only
 *      when the bills agree. A rule bringing up a defense bill and a stablecoin bill
 *      together is not about either, so it is left to the text classifier (null).
 */
class BillTopicResolver
{
    protected string $baseUrl;

    protected ?string $apiKey;

    public function __construct(
        protected IssueClassifierService $classifier,
        protected CongressLegislationImporter $legislation,
    ) {
        $this->baseUrl = rtrim((string) config('services.congress.base_url', 'https://api.congress.gov/v3'), '/');
        $this->apiKey = config('services.congress.api_key');
    }

    /**
     * @param  list<string>  $billKeys  e.g. ["119-hr-3633", "119-hres-580"]
     * @return array{topic_slug: string, bill_key: string, source: 'vote'|'bill'}|null
     */
    public function resolve(array $billKeys): ?array
    {
        foreach ($billKeys as $key) {
            if ($slug = $this->taggedVoteTopic($key)) {
                return ['topic_slug' => $slug, 'bill_key' => $key, 'source' => 'vote'];
            }
        }

        $topics = [];
        foreach ($billKeys as $key) {
            if ($slug = $this->bill($key)?->topic_key) {
                $topics[$slug] ??= $key;
            }
        }

        return count($topics) === 1
            ? ['topic_slug' => array_key_first($topics), 'bill_key' => reset($topics), 'source' => 'bill']
            : null;
    }

    /** Topic an editor gave a roll call on this bill; the most recent vote's tag wins. */
    public function taggedVoteTopic(string $key): ?string
    {
        if (! $parsed = CongressBill::parseKey($key)) {
            return null;
        }
        [$congress, $type, $number] = $parsed;

        // Chambers print bill numbers differently ("H R 3633", "S.J.Res. 59"); match on
        // the letters and digits alone.
        return CongressVote::query()
            ->where('congress', $congress)
            ->where('bill_number', 'like', "%{$number}")
            ->whereHas('topicTags')
            ->with('topicTags.topic')
            ->orderByDesc('voted_at')
            ->get()
            ->first(fn (CongressVote $vote) => strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $vote->bill_number)) === strtoupper($type.$number))
            ?->topicTags->first()?->topic?->slug;
    }

    /** The stored bill, fetched from Congress.gov the first time it is seen. */
    public function bill(string $key): ?CongressBill
    {
        $bill = CongressBill::firstWhere('bill_key', $key);
        if ($bill?->fetched_at || ! $this->apiKey || ! $parsed = CongressBill::parseKey($key)) {
            return $bill;
        }
        [$congress, $type, $number] = $parsed;

        try {
            $response = Http::timeout(20)->retry(2, 1000, throw: false)
                ->get("{$this->baseUrl}/bill/{$congress}/{$type}/{$number}", ['format' => 'json', 'api_key' => $this->apiKey]);
        } catch (\Throwable $e) {
            Log::info('BillTopicResolver: bill fetch failed', ['bill' => $key, 'error' => $e->getMessage()]);

            return $bill;
        }
        // A 404 is final (not a bill Congress.gov knows); other failures retry next run.
        if (! $response->successful() && $response->status() !== 404) {
            return $bill;
        }

        $title = trim((string) $response->json('bill.title'));
        $area = trim((string) $response->json('bill.policyArea.name'));
        // Title keywords are more specific than the policy area ("data centers" vs "Energy").
        $topic = $this->classifier->confidentKeywordMatch($title)['topic_slug'] ?? ($this->legislation->topicsFor($area, '')[0] ?? null);

        return CongressBill::updateOrCreate(['bill_key' => $key], [
            'title' => $title !== '' ? mb_substr($title, 0, 500) : null,
            'policy_area' => $area !== '' ? $area : null,
            'topic_key' => $topic,
            'fetched_at' => now(),
        ]);
    }
}
