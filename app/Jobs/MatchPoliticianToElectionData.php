<?php

namespace App\Jobs;

use App\Models\Politician;
use App\Services\PoliticianElectionMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MatchPoliticianToElectionData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $politicianId)
    {
    }

    public function handle(PoliticianElectionMatcher $matcher): void
    {
        $politician = Politician::find($this->politicianId);

        if (! $politician) {
            return;
        }

        $matcher->match($politician);
    }
}
