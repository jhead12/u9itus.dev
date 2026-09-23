@extends('standalone.layouts.dashboard')

@section('title', 'My Workspace')
@section('page-title', 'My Workspace')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-400 max-w-xl">
            Arrange the widgets you check most often — activity, local news matched to your interests,
            voting updates, and your campaigns. Drag a card by its handle to reorder, and use the width
            toggle to give it more room. Your layout is personal — no one else sees it.
        </p>

        @if($availableWidgets->isNotEmpty())
        <form method="POST" action="{{ route('citizen.workspace.widgets.store') }}" class="flex items-center gap-2">
            @csrf
            <select name="widget_key" class="rounded-lg bg-slate-800 border-slate-600 text-sm text-slate-200">
                @foreach($availableWidgets as $key => $meta)
                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="text-xs font-medium px-3 py-2 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/30 hover:bg-amber-500/20 transition">
                + Add widget
            </button>
        </form>
        @endif
    </div>

    @if($widgets->isEmpty())
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-8 text-center text-sm text-slate-500">
            No widgets yet. Add one above to get started.
        </div>
    @endif

    <div id="workspace-grid" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach($widgets as $widget)
            <div class="workspace-widget-card {{ $widget->width === 'full' ? 'lg:col-span-2' : '' }} bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden"
                 draggable="true"
                 data-widget-id="{{ $widget->id }}"
                 data-widget-width="{{ $widget->width }}">
                <div class="px-4 py-3 border-b border-slate-700/50 flex items-center justify-between gap-2 cursor-move widget-drag-handle">
                    <div class="flex items-center gap-2 min-w-0">
                        <svg class="w-4 h-4 text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                        <h3 class="text-sm font-semibold text-white truncate">{{ $widget->catalog['label'] }}</h3>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" class="widget-width-toggle text-[11px] px-2 py-1 rounded-md text-slate-400 hover:text-white hover:bg-slate-700/60 transition" title="Toggle width">
                            ⤢
                        </button>
                        <form method="POST" action="{{ route('citizen.workspace.widgets.destroy', $widget) }}" onsubmit="return confirm('Remove this widget from your workspace?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-[11px] px-2 py-1 rounded-md text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition" title="Remove">
                                ✕
                            </button>
                        </form>
                    </div>
                </div>
                <div class="p-4">
                    @include('standalone.citizen.workspace.widgets.'.$widget->widget_key, ['data' => $widget->data])
                </div>
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
(function () {
    const grid = document.getElementById('workspace-grid');
    if (!grid) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let dragged = null;

    grid.querySelectorAll('.workspace-widget-card').forEach((card) => {
        card.addEventListener('dragstart', () => {
            dragged = card;
            card.classList.add('opacity-40');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('opacity-40');
            dragged = null;
            saveLayout();
        });
        card.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (!dragged || dragged === card) return;
            const rect = card.getBoundingClientRect();
            const after = (e.clientY - rect.top) > rect.height / 2;
            card.parentNode.insertBefore(dragged, after ? card.nextSibling : card);
        });

        card.querySelector('.widget-width-toggle')?.addEventListener('click', () => {
            const isFull = card.classList.toggle('lg:col-span-2');
            card.dataset.widgetWidth = isFull ? 'full' : 'half';
            saveLayout();
        });
    });

    function saveLayout() {
        const widgets = Array.from(grid.querySelectorAll('.workspace-widget-card')).map((card, index) => ({
            id: parseInt(card.dataset.widgetId, 10),
            position: index,
            width: card.dataset.widgetWidth === 'full' ? 'full' : 'half',
        }));

        fetch('{{ route('citizen.workspace.widgets.layout') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ widgets }),
        }).catch(() => {});
    }
})();
</script>
@endpush
@endsection
