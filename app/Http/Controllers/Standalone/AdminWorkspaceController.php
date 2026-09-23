<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceWidget;
use App\Support\Workspace\WidgetCatalog;
use App\Support\Workspace\WorkspaceDataProvider;
use Illuminate\Http\Request;

/**
 * Per-user, drag-and-arrange dashboard: a set of widgets (see WidgetCatalog)
 * an admin/staff member picks and positions for themselves. Layout is
 * personal (keyed by user_id), unlike the fixed admin.dashboard stats page.
 */
class AdminWorkspaceController extends Controller
{
    public function index(Request $request, WorkspaceDataProvider $dataProvider)
    {
        $user = $request->user();

        $widgets = WorkspaceWidget::where('user_id', $user->id)
            ->orderBy('position')
            ->get();

        if ($widgets->isEmpty()) {
            $widgets = $this->seedDefaults($user->id);
        }

        $widgets = $widgets->map(function (WorkspaceWidget $widget) use ($dataProvider) {
            $widget->setAttribute('catalog', WidgetCatalog::all()[$widget->widget_key] ?? null);
            $widget->setAttribute('data', $dataProvider->forKey($widget->widget_key));
            return $widget;
        })->filter(fn (WorkspaceWidget $widget) => $widget->catalog !== null)->values();

        $availableWidgets = collect(WidgetCatalog::all())
            ->except($widgets->pluck('widget_key')->all());

        return view('standalone.admin.workspace.index', compact('widgets', 'availableWidgets'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'widget_key' => ['required', 'string', 'in:'.implode(',', WidgetCatalog::keys())],
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
            'width' => WidgetCatalog::defaultWidthFor($data['widget_key']),
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
        $rows = collect(WidgetCatalog::defaultKeys())->values()->map(function (string $key, int $position) use ($userId) {
            return WorkspaceWidget::create([
                'user_id' => $userId,
                'widget_key' => $key,
                'position' => $position,
                'width' => WidgetCatalog::defaultWidthFor($key),
            ]);
        });

        return $rows;
    }
}
