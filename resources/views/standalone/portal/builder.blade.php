<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Portal Builder — {{ $organization->name }}</title>
    @vite(['resources/js/portal-builder/app.jsx'])
</head>
<body style="margin:0;font-family:system-ui,sans-serif;">
    <header style="padding:1rem 1.5rem;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
        <div>
            <strong>{{ $organization->name }}</strong> — portal builder
            <span id="portal-builder-status" style="margin-left:1rem;color:#059669;">{{ session('status') }}</span>
        </div>
        <a href="{{ route('portal.show', $organization) }}" target="_blank" rel="noreferrer">View published portal ↗</a>
    </header>

    {{-- Plain Blade form for org-level settings (state/district scope, publish
         toggle) — deliberately server-rendered rather than folded into the
         Puck editor's JSON tree, since these govern which candidates/measures
         the data blocks pull in (see App\Services\PortalDataService), not the
         page layout itself. --}}
    <section style="padding:1rem 1.5rem;background:#f9fafb;border-bottom:1px solid #e5e7eb;">
        <form method="POST" action="{{ route('portal.builder.update', $organization) }}" style="display:flex;gap:1rem;align-items:end;flex-wrap:wrap;">
            @csrf
            @method('PUT')
            <label>
                State
                <input type="text" name="target_state" value="{{ $organization->target_state }}" maxlength="2" placeholder="CA" style="text-transform:uppercase;width:4rem;">
            </label>
            <label>
                District (optional)
                <input type="text" name="target_district" value="{{ $organization->target_district }}" placeholder="12">
            </label>
            <label>
                {{-- Hidden "0" ensures this form always submits portal_published
                     (unlike an unchecked checkbox, which sends nothing) — the
                     Puck editor's autosave never sends this field at all, so the
                     controller can tell "unpublish" apart from "not my concern". --}}
                <input type="hidden" name="portal_published" value="0">
                <input type="checkbox" name="portal_published" value="1" @checked($organization->portal_published)>
                Published
            </label>
            <button type="submit">Save settings</button>
        </form>
        <p style="color:#6b7280;font-size:0.875rem;">
            Organization type: {{ $orgTypes[$organization->org_type]['label'] ?? $organization->org_type }}
            @unless($organization->canEndorseCandidates())
                — candidate endorsements are disabled for this type (ballot-measure positions only).
            @endunless
        </p>
    </section>

    <div id="portal-root" data-mode="builder"></div>
    <script id="portal-builder-data" type="application/json">
        {{ Illuminate\Support\Js::from([
            'saveUrl' => route('portal.builder.update', $organization),
            'initialData' => $organization->portal_layout ?: ['content' => [], 'root' => []],
            'portalData' => $portalData,
            'canEndorseCandidates' => $organization->canEndorseCandidates(),
        ]) }}
    </script>

    {{-- Endorsement management — plain Blade CRUD (not part of the Puck tree).
         An EndorsementBadges block on the canvas above renders whatever is
         published here; see resources/js/portal-builder/blocks.jsx. --}}
    <section style="padding:1.5rem;border-top:1px solid #e5e7eb;max-width:640px;">
        <h2>Endorsements</h2>
        <ul>
            @forelse ($portalData['endorsements'] as $endorsement)
                <li style="margin-bottom:0.5rem;">
                    <strong>{{ $endorsement->label }}</strong>
                    ({{ $endorsement->position }})
                    — {{ $endorsement->politician->full_name ?? $endorsement->ballotMeasure->title ?? '' }}
                    <form method="POST" action="{{ route('portal.builder.endorsements.destroy', [$organization, $endorsement]) }}" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit">Remove</button>
                    </form>
                </li>
            @empty
                <li style="color:#6b7280;">No endorsements yet.</li>
            @endforelse
        </ul>

        <form method="POST" action="{{ route('portal.builder.endorsements.store', $organization) }}" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:end;">
            @csrf
            <label>
                Label
                <input type="text" name="label" required maxlength="128" placeholder="Vote YES on Prop 1">
            </label>
            <label>
                Position
                <select name="position">
                    <option value="endorse">Endorse</option>
                    <option value="oppose">Oppose</option>
                    <option value="neutral">Neutral</option>
                </select>
            </label>
            @if ($organization->canEndorseCandidates())
                <label>
                    Candidate (optional)
                    <select name="politician_id">
                        <option value="">—</option>
                        @foreach ($portalData['candidates'] as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->full_name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label>
                Ballot measure (optional)
                <select name="ballot_measure_id">
                    <option value="">—</option>
                    @foreach ($portalData['ballotMeasures'] as $measure)
                        <option value="{{ $measure->id }}">{{ $measure->title }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit">Add endorsement</button>
        </form>
    </section>
</body>
</html>
