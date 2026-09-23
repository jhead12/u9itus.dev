<div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    <div>
        <p class="text-2xl font-bold text-white">{{ number_format($data['total_users'] ?? 0) }}</p>
        <p class="text-xs text-slate-500 mt-1">total users</p>
    </div>
    <div>
        <p class="text-2xl font-bold text-white">{{ number_format($data['total_politicians'] ?? 0) }}</p>
        <p class="text-xs text-slate-500 mt-1">politicians</p>
    </div>
    <div>
        <p class="text-2xl font-bold text-white">{{ number_format($data['total_voters'] ?? 0) }}</p>
        <p class="text-xs text-slate-500 mt-1">voters</p>
    </div>
    <div>
        <p class="text-2xl font-bold {{ ($data['suspended_users'] ?? 0) > 0 ? 'text-orange-400' : 'text-white' }}">{{ number_format($data['suspended_users'] ?? 0) }}</p>
        <p class="text-xs text-slate-500 mt-1">suspended</p>
    </div>
</div>
