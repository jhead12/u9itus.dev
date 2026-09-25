<?php

namespace App\Http\Controllers\Standalone;

use App\Models\PoliticianEndorsement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Editor review of news-detected endorsements. Detection cannot tell who endorsed
 * whom, so nothing is shown on profiles, the map, or comparisons until an editor
 * confirms it as an endorsement of the candidate or of one of their bills.
 */
class AdminEndorsementController
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', PoliticianEndorsement::STATUS_DETECTED);
        $status = in_array($status, ['all', 'detected', 'confirmed', 'dismissed'], true) ? $status : 'detected';

        $endorsements = PoliticianEndorsement::query()
            ->with(['politician:id,full_name,slug,state,political_office', 'sourceArticle:id,headline,source_name,published_at', 'reviewedBy:id,name'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = trim((string) $request->query('q'));
                $query->where(fn ($query) => $query->where('endorser_name', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%")
                    ->orWhereHas('politician', fn ($query) => $query->where('full_name', 'like', "%{$search}%")));
            })
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        $stats = collect(['detected', 'confirmed', 'dismissed'])
            ->mapWithKeys(fn ($value) => [$value => PoliticianEndorsement::where('status', $value)->count()]);

        return view('standalone.admin.endorsements', compact('endorsements', 'stats', 'status'));
    }

    public function review(Request $request, PoliticianEndorsement $endorsement)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['confirm_candidate', 'confirm_bill', 'dismiss', 'return_to_review'])],
            'bill_title' => ['nullable', 'string', 'max:255'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);
        $billTitle = trim((string) ($data['bill_title'] ?? ''));
        if ($data['action'] === 'confirm_bill' && $billTitle === '') {
            throw ValidationException::withMessages(['bill_title' => 'Name the bill this endorsement supports.']);
        }

        $endorsement->update(match ($data['action']) {
            'confirm_candidate' => ['status' => PoliticianEndorsement::STATUS_CONFIRMED, 'kind' => PoliticianEndorsement::KIND_CANDIDATE, 'bill_title' => null],
            'confirm_bill' => ['status' => PoliticianEndorsement::STATUS_CONFIRMED, 'kind' => PoliticianEndorsement::KIND_BILL, 'bill_title' => $billTitle],
            'dismiss' => ['status' => PoliticianEndorsement::STATUS_DISMISSED],
            'return_to_review' => ['status' => PoliticianEndorsement::STATUS_DETECTED],
        } + [
            'reviewed_by_user_id' => $data['action'] === 'return_to_review' ? null : $request->user()->id,
            'reviewed_at' => $data['action'] === 'return_to_review' ? null : now(),
            'review_note' => $data['review_note'] ?? $endorsement->review_note,
        ]);
        // The public profile page is cached; clear it so the decision shows immediately.
        Cache::forget("profile.page.seo-v2.{$endorsement->politician_id}");

        $message = match ($data['action']) {
            'confirm_candidate' => 'Confirmed as an endorsement of the candidate.',
            'confirm_bill' => 'Confirmed as an endorsement of their bill.',
            'dismiss' => 'Dismissed. It will not be shown.',
            default => 'Returned to review.',
        };

        return back()->with('success', $message);
    }
}
