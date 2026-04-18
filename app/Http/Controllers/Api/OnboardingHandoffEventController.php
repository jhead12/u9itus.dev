<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingHandoffEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingHandoffEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:voter,politician'],
            'event_type' => ['required', 'in:opened,dismissed'],
            'widget_key' => ['required', 'string', 'max:120'],
            'context' => ['nullable', 'array'],
        ]);

        OnboardingHandoffEvent::query()->create([
            'user_id' => $user->id,
            'role' => $validated['role'],
            'event_type' => $validated['event_type'],
            'widget_key' => $validated['widget_key'],
            'context' => $validated['context'] ?? [],
        ]);

        return response()->json(['ok' => true]);
    }
}

