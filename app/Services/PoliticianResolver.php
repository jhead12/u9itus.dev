<?php

namespace App\Services;

use App\Models\Politician;

/**
 * Resolves a Politician from the loose slug/name/state/office params the
 * public map endpoints accept. Shared by MapCandidateOverviewController and
 * MapCandidateEconomyController so both endpoints match candidates the same
 * way.
 */
class PoliticianResolver
{
    public function resolve(string $slug, string $fullName, string $state, string $office): ?Politician
    {
        if ($slug !== '') {
            return Politician::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();
        }

        if ($fullName === '') {
            return null;
        }

        return Politician::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
            ->when($state !== '', fn ($q) => $q->whereRaw("UPPER(COALESCE(state, '')) = ?", [$state]))
            ->when($office !== '', fn ($q) => $q->whereRaw("LOWER(COALESCE(political_office, '')) LIKE ?", ['%' . strtolower($office) . '%']))
            ->first();
    }
}
