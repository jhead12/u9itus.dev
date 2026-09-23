<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceWidget;
use App\Support\Workspace\CitizenWidgetCatalog;
use App\Support\Workspace\CitizenWorkspaceDataProvider;
use Illuminate\Http\Request;

/**
 * Per-citizen, drag-and-arrange "My Workspace" page — the citizen analogue
 * of AdminWorkspaceController. Layout is personal (keyed by user_id) and
 * shares the same workspace_widgets table; see CitizenWidgetCatalog for why
 * every widget_key here is 'citizen_'-prefixed.
 */
class CitizenWorkspaceController extends Controller
{
    public function index(Request $request, CitizenWorkspaceDataProvider $dataProvider)
    {
        $user = $request->user();
        $citizen = $user->citizen;

        abort_unless($citizen, 403, 'No citizen profile found.');

        $widgets = WorkspaceWidget::where('user_id', $user->id)
            ->whereIn('widget_key', CitizenWidgetCatalog::keys())
            ->orderBy('position')
            ->get();

        if ($widgets->isEmpty()) {
            $widgets = $this->seedDefaults($user->id);
        }

        $widgets = $widgets->map(function (WorkspaceWidget $widget) use ($dataProvider, $citizen) {
            $widget->setAttribute('catalog', CitizenWidgetCatalog::all()[$widget->widget_key] ?? null);
            $widget->setAttribute('data', $dataProvider->forKey($widget->widget_key, $citizen));
            return $widget;
        })->filter(fn (WorkspaceWidget $widget) => $widget->catalog !== null)->values();

        $availableWidgets = collect(CitizenWidgetCatalog::all())
            ->except($widgets->pluck('widget_key')->all());

        return view('standalone.citizen.workspace.index', compact('widgets', 'availableWidgets'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'widget_key' => ['required', 'string', 'in:'.implode(',', CitizenWidgetCatalog::keys())],
        ]);

        $user = $request->user();

        if (WorkspaceWidget::where('user_id', $user->id)->where('widget_key', $data['widget_key'])->exists()) {
            return back();
        }

        $nextPosition = (int) (WorkspaceWidget::where('user_id', $user->id)->max('position') ?? -1) + 1;

        WorkspaceWidget::create([
            'user_id' => $user->id,
            'widget_key' => $data['widget_key'],
            'position' => $nextPosition,
            'width' => CitizenWidgetCatalog::defaultWidthFor($data['widget_key']),
        ]);

        return back()->with('success', 'Widget added.');
    }

    public function updateLayout(Request $request)
    {
        $data = $request->validate([
            'widgets' => ['required', 'array'],
            'widgets.*.id' => ['required', 'integer'],
            'widgets.*.position' => ['required', 'integer', 'min:0'],
            'widgets.*.width' => ['required', 'string', 'in:half,full'],
        ]);

        $user = $request->user();
        $ownedIds = WorkspaceWidget::where('user_id', $user->id)->pluck('id')->all();

        foreach ($data['widgets'] as $entry) {
            if (! in_array($entry['id'], $ownedIds, true)) {
                continue;
            }
            WorkspaceWidget::where('id', $entry['id'])->update([
                'position' => $entry['position'],
                'width' => $entry['width'],
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    public function destroy(Request $request, WorkspaceWidget $widget)
    {
        abort_unless($widget->user_id === $request->user()->id, 403);
        $widget->delete();

        return back()->with('success', 'Widget removed.');
    }

    private function seedDefaults(int $userId)
    {
        return collect(CitizenWidgetCatalog::defaultKeys())->values()->map(function (string $key, int $position) use ($userId) {
            return WorkspaceWidget::create([
                'user_id' => $userId,
                'widget_key' => $key,
                'position' => $position,
                'width' => CitizenWidgetCatalog::defaultWidthFor($key),
            ]);
        });
    }
}
