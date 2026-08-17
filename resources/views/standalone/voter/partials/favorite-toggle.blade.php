@php
    /**
     * Favorite/unfavorite toggle for a Cause or Ballot Measure show page.
     * Server renders the form in the correct state (POST store, or
     * POST + DELETE destroy). JS hijacks submit and calls the JSON endpoint.
     *
     * Required vars:
     *   $isFavorited  bool
     *   $storeRoute   string  route to store (POST)
     *   $destroyRoute string  route to destroy (DELETE)
     *   $followLabel  string  e.g. "Follow this Cause"
     *   $followingLabel string e.g. "Following"
     */
@endphp

<form data-favorite-toggle
      data-store-route="{{ $storeRoute }}"
      data-destroy-route="{{ $destroyRoute }}"
      method="POST"
      action="{{ $isFavorited ? $destroyRoute : $storeRoute }}"
      class="inline-block">
    @csrf
    @if($isFavorited)
        @method('DELETE')
    @endif
    <button type="submit"
            data-follow-label="{{ $followLabel }}"
            data-following-label="{{ $followingLabel }}"
            class="inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold transition {{ $isFavorited
                ? 'bg-slate-700 hover:bg-slate-600 text-white'
                : 'bg-emerald-600 hover:bg-emerald-500 text-white' }}">
        @if($isFavorited)
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27l-5.18 3.04 1.4-5.92L3.5 9.5l6.06-.52L12 3.5l2.44 5.48 6.06.52-4.72 4.84 1.4 5.92z"/></svg>
            <span>{{ $followingLabel }}</span>
        @else
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.539 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
            <span>{{ $followLabel }}</span>
        @endif
    </button>
</form>

@push('scripts')
<script>
    (function () {
        const form = document.querySelector('form[data-favorite-toggle]');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = form.querySelector('button');
            const followLabel = btn.dataset.followLabel;
            const followingLabel = btn.dataset.followingLabel;
            const wasFavorited = form.querySelector('input[name="_method"][value="DELETE"]') !== null;

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: new FormData(form),
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data || !data.ok) throw new Error('bad response');
                const nowFavorited = !wasFavorited;

                // Flip the form action + method for the next toggle.
                form.action = nowFavorited
                    ? form.dataset.destroyRoute || form.getAttribute('data-destroy-route')
                    : form.dataset.storeRoute || form.getAttribute('data-store-route');
                let methodInput = form.querySelector('input[name="_method"]');
                if (nowFavorited && !methodInput) {
                    methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = 'DELETE';
                    form.appendChild(methodInput);
                } else if (!nowFavorited && methodInput) {
                    methodInput.remove();
                }

                // Swap button style + label.
                btn.className = 'inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold transition ' +
                    (nowFavorited ? 'bg-slate-700 hover:bg-slate-600 text-white' : 'bg-emerald-600 hover:bg-emerald-500 text-white');
                btn.innerHTML = nowFavorited
                    ? '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27l-5.18 3.04 1.4-5.92L3.5 9.5l6.06-.52L12 3.5l2.44 5.48 6.06.52-4.72 4.84 1.4 5.92z"/></svg><span>' + followingLabel + '</span>'
                    : '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.539 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg><span>' + followLabel + '</span>';

                if (window.toast) window.toast(nowFavorited ? followingLabel : 'Unfollowed', nowFavorited ? 'success' : 'info');
            }).catch(function () {
                if (window.toast) window.toast('Could not update. Try again.', 'error');
            });
        });
    })();
</script>
@endpush