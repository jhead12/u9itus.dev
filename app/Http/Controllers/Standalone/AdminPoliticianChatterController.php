<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use App\Models\PoliticianChatterItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class AdminPoliticianChatterController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', PoliticianChatterItem::MODERATION_PENDING);
        $allowedStatuses = ['all', 'pending', 'published', 'rejected', 'archived'];
        $status = in_array($status, $allowedStatuses, true) ? $status : 'pending';

        $items = PoliticianChatterItem::query()
            ->with(['politician:id,full_name,slug', 'reviewedBy:id,name', 'moderationLogs.admin:id,name'])
            ->when($status !== 'all', fn ($query) => $query->where('moderation_status', $status))
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = trim((string) $request->query('q'));
                $query->where(fn ($query) => $query->where('headline', 'like', "%{$search}%")
                    ->orWhere('source_author', 'like', "%{$search}%")
                    ->orWhereHas('politician', fn ($query) => $query->where('full_name', 'like', "%{$search}%")));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = collect(['pending', 'published', 'rejected', 'archived'])
            ->mapWithKeys(fn ($value) => [$value => PoliticianChatterItem::where('moderation_status', $value)->count()]);

        $politicians = Politician::query()
            ->where(fn ($query) => $query->where('is_active', true)
                ->orWhereIn('id', $items->getCollection()->pluck('politician_id')))
            ->orderBy('full_name')->get(['id', 'full_name', 'state', 'political_office', 'party_affiliation']);

        return view('standalone.admin.politician-chatter', compact('items', 'stats', 'status', 'politicians'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $data['engagement_metrics'] = $this->metrics($data);
        unset($data['likes'], $data['reposts'], $data['comments'], $data['views']);
        $data['moderation_status'] = PoliticianChatterItem::MODERATION_PENDING;

        $item = PoliticianChatterItem::create($data);
        $this->log($item, 'collected', null, 'pending', $request->input('moderation_note'));

        return back()->with('success', 'Chatter source added to the review queue.');
    }

    public function update(Request $request, PoliticianChatterItem $chatter)
    {
        abort_if($chatter->moderation_status === 'published' && ! \App\Support\AdminAccess::allowed($request->user(), 'chatter.publish'), 403);
        $data = $this->validated($request);
        $before = $chatter->only(array_keys($data));
        $data['engagement_metrics'] = $this->metrics($data);
        unset($data['likes'], $data['reposts'], $data['comments'], $data['views']);

        $chatter->update($data);
        $this->log($chatter, 'edited', $chatter->moderation_status, $chatter->moderation_status, $request->input('moderation_note'), $before);
        $this->forgetProfile($chatter);

        return back()->with('success', 'Chatter item updated.');
    }

    public function moderate(Request $request, PoliticianChatterItem $chatter)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['publish', 'reject', 'archive', 'return_to_review'])],
            'moderation_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = match ($data['action']) {
            'publish' => 'published', 'reject' => 'rejected', 'archive' => 'archived', default => 'pending',
        };
        $from = $chatter->moderation_status;
        $chatter->update([
            'moderation_status' => $target,
            'reviewed_by_user_id' => auth()->id(),
            'reviewed_at' => now(),
            'published_at' => $target === 'published' ? ($chatter->published_at ?? now()) : null,
        ]);
        $this->log($chatter, $data['action'], $from, $target, $data['moderation_note'] ?? null);
        $this->forgetProfile($chatter);

        return back()->with('success', 'Chatter item moved to '.$target.'.');
    }

    private function validated(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'politician_id' => [Rule::requiredIf($creating), 'sometimes', 'integer', 'exists:politicians,id'],
            'platform' => ['required', Rule::in(array_keys(PoliticianChatterItem::PLATFORMS))],
            'source_url' => [
                'required', 'url:http,https', 'max:1000',
                $this->sourceUrlRule($request),
            ],
            'source_author' => ['nullable', 'string', 'max:191'],
            'source_published_at' => ['nullable', 'date'],
            'headline' => ['required', 'string', 'max:240'],
            'summary' => ['required', 'string', 'max:2000'],
            'claim_status' => ['required', Rule::in(array_keys(PoliticianChatterItem::CLAIM_STATUSES))],
            'admin_notes' => ['nullable', 'string', 'max:3000'],
            'expires_at' => ['nullable', 'date'],
            'likes' => ['nullable', 'integer', 'min:0'],
            'reposts' => ['nullable', 'integer', 'min:0'],
            'comments' => ['nullable', 'integer', 'min:0'],
            'views' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function sourceUrlRule(Request $request): Unique
    {
        $rule = Rule::unique('politician_chatter_items', 'source_url')
            ->where(fn ($query) => $query->where('politician_id', $request->integer('politician_id')));

        $current = $request->route('chatter');

        return $current instanceof PoliticianChatterItem ? $rule->ignore($current->id) : $rule;
    }

    private function metrics(array $data): ?array
    {
        $metrics = collect(['likes', 'reposts', 'comments', 'views'])
            ->mapWithKeys(fn ($key) => [$key => isset($data[$key]) ? (int) $data[$key] : null])
            ->filter(fn ($value) => $value !== null)->all();

        return $metrics ?: null;
    }

    private function log(PoliticianChatterItem $item, string $action, ?string $from, ?string $to, ?string $note = null, array $changes = []): void
    {
        $item->moderationLogs()->create([
            'admin_user_id' => auth()->id(), 'action' => $action, 'from_status' => $from,
            'to_status' => $to, 'changes' => $changes ?: null, 'note' => $note,
        ]);
    }

    private function forgetProfile(PoliticianChatterItem $item): void
    {
        Cache::forget("profile.page.seo-v2.{$item->politician_id}");
    }
}
