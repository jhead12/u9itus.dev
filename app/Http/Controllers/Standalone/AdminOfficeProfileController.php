<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use App\Models\PoliticianOfficeProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin: manage civic office profile data for politicians/candidates.
 *
 * These profiles are the data source for the voter-facing "About This Office"
 * popup shown while watching campaign videos. The goal is to help every
 * US community understand what each elected or appointed position does and
 * how it affects their daily lives.
 */
class AdminOfficeProfileController extends Controller
{
    /**
     * List all politicians with their office profile status.
     */
    public function index(Request $request): View
    {
        $query = Politician::with('officeProfile')
            ->orderBy('full_name');

        // Filter by profile completion
        if ($request->filled('filter')) {
            match ($request->filter) {
                'missing'  => $query->doesntHave('officeProfile'),
                'complete' => $query->has('officeProfile'),
                'verified' => $query->whereHas('officeProfile', fn ($q) => $q->where('is_verified', true)),
                default    => null,
            };
        }

        // Search by name or office
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($builder) use ($q) {
                $builder->where('full_name', 'like', "%{$q}%")
                        ->orWhere('political_office', 'like', "%{$q}%")
                        ->orWhere('state', 'like', "%{$q}%");
            });
        }

        $politicians = $query->paginate(40)->withQueryString();

        $stats = [
            'total'     => Politician::count(),
            'complete'  => Politician::has('officeProfile')->count(),
            'verified'  => Politician::whereHas('officeProfile', fn ($q) => $q->where('is_verified', true))->count(),
        ];

        return view('standalone.admin.office-profiles.index', compact('politicians', 'stats'));
    }

    /**
     * Show the create/edit form for a politician's office profile.
     * The form doubles as "edit" when a profile already exists.
     */
    public function edit(Politician $politician): View
    {
        $profile = $politician->officeProfile ?? new PoliticianOfficeProfile([
            'politician_id' => $politician->id,
        ]);

        return view('standalone.admin.office-profiles.edit', compact('politician', 'profile'));
    }

    /**
     * Upsert (create or update) a politician's office profile.
     */
    public function update(Request $request, Politician $politician): RedirectResponse
    {
        $validated = $request->validate([
            'office_title'              => ['required', 'string', 'max:120'],
            'governance_level'          => ['nullable', 'string', 'max:60'],
            'jurisdiction'              => ['nullable', 'string', 'max:120'],
            'how_elected_or_appointed'  => ['nullable', 'string', 'max:80'],
            'term_length_years'         => ['nullable', 'integer', 'min:1', 'max:99'],
            'seats_in_body'             => ['nullable', 'integer', 'min:1'],
            'annual_salary_min'         => ['nullable', 'integer', 'min:0'],
            'annual_salary_max'         => ['nullable', 'integer', 'min:0'],
            'salary_source_note'        => ['nullable', 'string', 'max:255'],
            'role_summary'              => ['nullable', 'string', 'max:2000'],
            'community_impact'          => ['nullable', 'string', 'max:2000'],
            'key_duties'                => ['nullable', 'string'],  // textarea, split by newline
            'powers_and_limits'         => ['nullable', 'string'],  // textarea, split by newline
            'source_url'                => ['nullable', 'url', 'max:512'],
            'is_verified'               => ['boolean'],
        ]);

        // Convert salary dollars → cents for storage
        if (isset($validated['annual_salary_min'])) {
            $validated['annual_salary_min'] = (int) round($validated['annual_salary_min'] * 100);
        }
        if (isset($validated['annual_salary_max'])) {
            $validated['annual_salary_max'] = (int) round($validated['annual_salary_max'] * 100);
        }

        // Convert textarea newlines → JSON arrays
        $validated['key_duties'] = $this->parseTextareaToArray($validated['key_duties'] ?? '');
        $validated['powers_and_limits'] = $this->parseTextareaToArray($validated['powers_and_limits'] ?? '');

        // Handle is_verified stamp
        $wasVerified = $politician->officeProfile?->is_verified ?? false;
        if (! $wasVerified && ($validated['is_verified'] ?? false)) {
            $validated['data_verified_at'] = now();
        }

        try {
            $politician->officeProfile()->updateOrCreate(
                ['politician_id' => $politician->id],
                $validated
            );

            Log::info('AdminOfficeProfileController: upserted office profile', [
                'politician_id' => $politician->id,
                'admin_id'      => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            Log::error('AdminOfficeProfileController: failed to save office profile', [
                'politician_id' => $politician->id,
                'error'         => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to save office profile. Please try again.');
        }

        return redirect()
            ->route('admin.office-profiles.index')
            ->with('success', "Office profile for {$politician->full_name} saved successfully.");
    }

    /**
     * Mark a profile as verified (or un-verify).
     */
    public function toggleVerified(Politician $politician): RedirectResponse
    {
        $profile = $politician->officeProfile;

        if (! $profile) {
            return back()->with('error', 'No office profile exists for this politician yet.');
        }

        $profile->is_verified      = ! $profile->is_verified;
        $profile->data_verified_at = $profile->is_verified ? now() : null;
        $profile->save();

        $label = $profile->is_verified ? 'verified' : 'un-verified';

        return back()->with('success', "Office profile for {$politician->full_name} {$label}.");
    }

    /**
     * Delete a politician's office profile.
     */
    public function destroy(Politician $politician): RedirectResponse
    {
        $politician->officeProfile?->delete();

        return back()->with('success', "Office profile for {$politician->full_name} deleted.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Split a textarea (one item per line) into a clean JSON-ready array.
     * Blank lines and lines with only whitespace are discarded.
     */
    private function parseTextareaToArray(string $raw): array
    {
        return array_values(
            array_filter(
                array_map('trim', explode("\n", str_replace("\r\n", "\n", $raw))),
                fn ($line) => $line !== ''
            )
        );
    }
}
