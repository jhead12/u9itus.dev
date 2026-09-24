<?php

/*
|--------------------------------------------------------------------------
| State- and city-specific office descriptions
|--------------------------------------------------------------------------
|
| An office's powers differ by state and city: a New York City mayor runs
| city agencies and the budget, while many California cities have a
| council–manager government with a largely ceremonial mayor. Entries here
| replace App\Support\OfficeGlossary's general description for that place.
|
| Every entry must cite an official source (state constitution, city
| charter, or state/local government page) and the date it was reviewed.
| Do not add an entry without one; the general description is shown instead.
|
| Keys: two-letter state => office slug (see OfficeGlossary::ENTRIES), and
| 'cities' => city name => office slug, for city offices.
|
| 'CA' => [
|     'lieutenant-governor' => [
|         'description' => '…',
|         'source_label' => 'California Constitution, Article V',
|         'source_url' => 'https://…',
|         'reviewed_at' => '2026-09-24',
|     ],
|     'cities' => [
|         'Oakland' => ['mayor' => [ … same fields … ]],
|     ],
| ],
|
*/

return [
    'overrides' => [],
];
