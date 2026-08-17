<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\PoliticianTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin: manage the issue-area topic taxonomy (Healthcare, Climate Action,
 * etc.) used to tag campaigns, causes, and voter badges.
 */
class AdminTopicController extends Controller
{
    public function index(Request $request): View
    {
        $query = PoliticianTopic::query()->orderBy('sort_order');

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                        ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        if ($request->filled('filter')) {
            match ($request->filter) {
                'active'   => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                default    => null,
            };
        }

        $topics = $query->paginate(40)->withQueryString();

        return view('standalone.admin.topics.index', compact('topics'));
    }

    public function create(): View
    {
        $topic = new PoliticianTopic();

        return view('standalone.admin.topics.create', compact('topic'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            PoliticianTopic::create($validated);
        } catch (\Throwable $e) {
            Log::error('AdminTopicController: failed to create topic', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Failed to create topic. Please try again.');
        }

        return redirect()->route('admin.topics.index')->with('success', 'Topic created successfully.');
    }

    public function edit(PoliticianTopic $topic): View
    {
        return view('standalone.admin.topics.edit', compact('topic'));
    }

    public function update(Request $request, PoliticianTopic $topic): RedirectResponse
    {
        $validated = $this->validated($request, $topic);

        try {
            $topic->update($validated);
        } catch (\Throwable $e) {
            Log::error('AdminTopicController: failed to update topic', ['topic_id' => $topic->id, 'error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Failed to save topic. Please try again.');
        }

        return redirect()->route('admin.topics.index')->with('success', "Topic \"{$topic->name}\" saved successfully.");
    }

    public function destroy(PoliticianTopic $topic): RedirectResponse
    {
        $topic->delete();

        return redirect()->route('admin.topics.index')->with('success', 'Topic deleted.');
    }

    private function validated(Request $request, ?PoliticianTopic $topic = null): array
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255', 'unique:politician_topics,name' . ($topic ? ",{$topic->id}" : '')],
            'slug'              => ['required', 'string', 'max:255', 'unique:politician_topics,slug' . ($topic ? ",{$topic->id}" : '')],
            'description'       => ['nullable', 'string'],
            'icon'              => ['nullable', 'string', 'max:64'],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
            'is_active'         => ['boolean'],
            'badge_icon_url'    => ['nullable', 'string', 'max:2048'],
            'badge_color'       => ['nullable', 'string', 'max:7'],
            'voter_selectable'  => ['boolean'],
            'auto_earned_only'  => ['boolean'],
        ]);

        // Checkboxes are absent from the request entirely when unchecked.
        $validated['is_active'] = $request->boolean('is_active');
        $validated['voter_selectable'] = $request->boolean('voter_selectable');
        $validated['auto_earned_only'] = $request->boolean('auto_earned_only');

        return $validated;
    }
}
