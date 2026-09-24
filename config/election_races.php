<?php

/*
|--------------------------------------------------------------------------
| Which statewide races are on the ballot, per election year
|--------------------------------------------------------------------------
|
| Read by App\Support\RaceCalendar. A news-discovered "candidate" for a race a
| state is not holding (a U.S. Senate candidate in New York in 2026) is a
| national headline filed under the wrong state, whatever the name.
|
| Keys under each year match CandidateCorroboration::officeKind(). A year or
| office missing here means "unknown", and no rule acts on it — add the next
| cycle before its discovery runs start. Special elections for vacated seats
| must be added by hand when they are called.
|
*/

return [

    2026 => [
        // Class 2 seats, plus the Ohio and Florida specials (Vance and Rubio seats).
        'senate' => [
            'AL', 'AK', 'AR', 'CO', 'DE', 'FL', 'GA', 'ID', 'IL', 'IA', 'KS', 'KY',
            'LA', 'ME', 'MA', 'MI', 'MN', 'MS', 'MT', 'NE', 'NH', 'NJ', 'NM', 'NC',
            'OH', 'OK', 'OR', 'RI', 'SC', 'SD', 'TN', 'TX', 'VA', 'WV', 'WY',
        ],

        'Governor' => [
            'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'FL', 'GA', 'HI', 'ID', 'IL',
            'IA', 'KS', 'ME', 'MD', 'MA', 'MI', 'MN', 'NE', 'NV', 'NH', 'NM', 'NY',
            'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'VT', 'WI', 'WY',
        ],
    ],

];
