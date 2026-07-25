<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        Organization::query()->updateOrCreate(
            ['slug' => 'aipac'],
            [
                'name' => 'AIPAC (American Israel Public Affairs Committee)',
                'org_type' => 'pac',
                'pac_group_key' => 'aipac_pro_israel',
                'website_url' => 'https://www.aipac.org',
                'description' => 'A pro-Israel advocacy organization and one of the largest PACs by contribution volume in U.S. federal elections.',
                'logo_url' => null,
                'verification_status' => 'unverified',
                'is_active' => true,
            ]
        );
    }
}
