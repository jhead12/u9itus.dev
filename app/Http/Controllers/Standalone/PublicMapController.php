<?php

namespace App\Http\Controllers\Standalone;

use App\Models\Politician;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicMapController
{
    public function __invoke(Request $request): View
    {
        $seo = [
            'seoTitle' => 'U.S. Regional Map – U9itus',
            'seoDescription' => 'Explore an interactive 3D map of all 50 U.S. states and 435 congressional districts. Discover politicians, candidates, and civic officials for your area.',
            'seoCanonical' => url('/map'),
        ];

        $stateInput = $request->query('state');
        $districtInput = $request->query('district');
        $state = is_string($stateInput) ? strtoupper(trim($stateInput)) : '';
        $district = is_string($districtInput) ? strtoupper(trim($districtInput)) : '';
        $stateName = config('u9itus.us_states.'.$state);

        if (! is_string($stateName) || ! preg_match('/^(AL|\d{1,2})$/', $district)) {
            return view('standalone.public.us-map', compact('seo'));
        }

        $district = $district === 'AL' || (int) $district === 0 ? 'AL' : (string) (int) $district;
        $code = $state.'-'.$district;
        $label = $district === 'AL'
            ? $stateName.' At-Large Congressional District'
            : $stateName.' Congressional District '.$district;
        $aliases = $district === 'AL' ? ['AL', '0', '00'] : [$district, str_pad($district, 2, '0', STR_PAD_LEFT)];
        $aliases = array_merge($aliases, array_map(fn ($value) => $state.'-'.$value, $aliases));

        // Resolve the sitting representative independently of the shared slug:
        // a challenger or an old link must not replace the current officeholder.
        $leader = Politician::query()->publiclyVisible()
            ->whereRaw('UPPER(state) = ?', [$state])
            ->whereRaw('LOWER(governance_level) = ?', ['federal'])
            ->whereIn('district', $aliases)
            ->where(function ($query) {
                $query->whereIn('term_status', ['seated', 'current'])
                    ->orWhere(fn ($query) => $query->where('term_status', 'active')->where('is_running_candidate', false));
            })
            ->where(fn ($query) => $query->whereNull('term_ends_on')->orWhereDate('term_ends_on', '>=', today()))
            ->where(function ($query) {
                $query->whereRaw('LOWER(political_office) LIKE ?', ['%representative%'])
                    ->orWhereRaw('LOWER(political_office) LIKE ?', ['%u.s. house%']);
            })
            ->orderByDesc('verified_official')->orderByDesc('updated_at')->orderBy('id')
            ->first();

        $population = DB::table('district_populations')->where('state', $state)
            ->where('district_number', $district === 'AL' ? 0 : (int) $district)
            ->orderByDesc('census_year')->first();

        $description = $label.' ('.$code.').';
        if ($population) {
            $description .= ' Population: '.number_format($population->total_population).' ('.$population->census_year.' Census data).';
        }
        if ($leader) {
            $description .= ' Represented by '.$leader->full_name.'.';
        }
        $description .= ' Explore local demographics, the economy, and candidates on U9itus.';

        $seo = [
            'seoTitle' => $label.($leader ? ' – '.$leader->full_name : '').' | U9itus',
            'seoDescription' => $description,
            // Tracking and politician selection do not create separate district identities.
            'seoCanonical' => url('/map').'?'.http_build_query(['state' => $state, 'district' => $district]),
        ];
        $photo = trim((string) $leader?->profile_photo_url);
        if ($photo !== '' && (preg_match('~^https?://~i', $photo) || ! preg_match('~^[a-z][a-z0-9+.-]*:~i', $photo))) {
            $seo['ogImage'] = url($photo);
            $seo['ogImageAlt'] = $leader->full_name.' — '.$label;
            $seo['twitterCard'] = 'summary';
        }

        return view('standalone.public.us-map', compact('seo'));
    }
}
