<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Cause;
use App\Models\PoliticianTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin: manage Causes — specific, nameable issues under a Topic that voters
 * can favorite (e.g. "Expand Medicaid in Texas" under "Healthcare").
 */
class AdminCauseController extends Controller
{
    public function index(Request $request): View
    {
        $query = Cause::with('topic')->orderByDesc('created_at');

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', "%{$q}%")
                        ->orWhere('state', 'like', "%{$q}%");
            });
        }

        if ($request->filled('topic_id')) {
            $query->where('topic_id', $request->topic_id);
        }

        $causes = $query->paginate(40)->withQueryString();
        $topics = PoliticianTopic::active()->get();

        return view('standalone.admin.causes.index', compact('causes', 'topics'));
    }

    public function create(): View
    {
        $cause = new Cause();
        $topics = PoliticianTopic::active()->get();

        return view('standalone.admin.causes.create', compact('cause', 'topics'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            Cause::create($validated);
        } catch (\Throwable $e) {
            Log::error('AdminCauseController: failed to create cause', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Failed to create cause. Please try again.');
        }

        return redirect()->route('admin.causes.index')->with('success', 'Cause created successfully.');
    }

    public function edit(Cause $cause): View
    {
        $topics = PoliticianTopic::active()->get();

        return view('standalone.admin.causes.edit', compact('cause', 'topics'));
    }

    public function update(Request $request, Cause $cause): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            $cause->update($validated);
        } catch (\Throwable $e) {
            Log::error('AdminCauseController: failed to update cause', ['cause_id' => $cause->id, 'error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Failed to save cause. Please try again.');
        }

        return redirect()->route('admin.causes.index')->with('success', "Cause \"{$cause->title}\" saved successfully.");
    }

    public function destroy(Cause $cause): RedirectResponse
    {
        $cause->delete();

        return redirect()->route('admin.causes.index')->with('success', 'Cause deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'topic_id'     => ['required', 'integer', 'exists:politician_topics,id'],
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'state'        => ['nullable', 'string', 'size:2'],
            'county'       => ['nullable', 'string', 'max:100'],
            'status'       => ['required', 'string', 'max:20'],
            'source_url'   => ['nullable', 'url', 'max:2048'],
        ]);
    }
}
