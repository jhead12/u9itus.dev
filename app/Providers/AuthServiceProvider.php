<?php

namespace App\Providers;

use App\Models\AdAssignment;
use App\Models\Campaign;
use App\Policies\AdAssignmentPolicy;
use App\Policies\CampaignPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Campaign::class => CampaignPolicy::class,
        AdAssignment::class => AdAssignmentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
