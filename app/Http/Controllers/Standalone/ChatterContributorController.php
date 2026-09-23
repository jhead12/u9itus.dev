<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use App\Models\PoliticianChatterItem;
use App\Support\ChatterContributorAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChatterContributorController extends Controller
{
    private function authorizeContributor(Request $request): void
    {
        abort_unless(ChatterContributorAccess::allowed($request->user()->fresh()), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeContributor($request);
        $politicians = Politician::where('is_active', true)->orderBy('full_name')
            ->get(['id', 'full_name', 'state', 'political_office', 'party_affiliation']);
        $items = PoliticianChatterItem::where('submitted_by_user_id', $request->user()->id)
            ->with('politician:id,full_name')->latest()->paginate(15);
        return view('standalone.contributor.chatter', compact('politicians', 'items'));
    }

    public function store(Request $request)
    {
        $this->authorizeContributor($request);
        $data = $request->validate([
            'politician_id' => ['required', 'integer', Rule::exists('politicians', 'id')->where('is_active', true)],
            'platform' => ['required', Rule::in(array_keys(PoliticianChatterItem::PLATFORMS))],
            'source_url' => ['required', 'url:http,https', 'max:1000', function ($attribute, $value, $fail) {
                $host = parse_url($value, PHP_URL_HOST);
                if (! $host || ! str_contains($host, '.') || parse_url($value, PHP_URL_USER)
                    || preg_match('/\.(local|localhost|internal|test)$/i', $host)
                    || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
                    $fail('Use a public source URL without embedded login credentials.');
                }
            }, Rule::unique('politician_chatter_items', 'source_url')->where('politician_id', $request->integer('politician_id'))],
            'headline' => ['required', 'string', 'max:240'],
            'contributor_notes' => ['required', 'string', 'max:2000'],
            'source_excerpt' => ['nullable', 'string', 'max:2000'],
            'public_source' => ['accepted'],
        ]);
        try {
            DB::transaction(function () use ($request, $data) {
                $item = new PoliticianChatterItem;
                $item->politician_id = $data['politician_id'];
                $item->platform = $data['platform'];
                $item->source_url = $data['source_url'];
                $item->headline = $data['headline'];
                $item->contributor_notes = $data['contributor_notes'];
                $item->source_excerpt = $data['source_excerpt'] ?? null;
                $item->submitted_by_user_id = $request->user()->id;
                $item->moderation_status = 'pending';
                $item->claim_status = 'unverified';
                $item->save();
                $item->moderationLogs()->create([
                    'admin_user_id' => $request->user()->id, 'action' => 'contributor_submitted',
                    'to_status' => 'pending',
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['source_url' => 'This source has already been submitted for this politician.']);
        }
        return to_route('contributor.chatter.index')->with('success', 'Source submitted for editorial review. Nothing has been published.');
    }
}
