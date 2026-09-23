<?php

use App\Models\User;
use App\Models\Voter;
use Spatie\Permission\Models\Role;

function studySystemsVoter(): User
{
    Role::findOrCreate('voter', 'web');
    $user = User::factory()->create(['user_type' => 'voter', 'platform' => 'standalone', 'email_verified_at' => now()]);
    $user->assignRole('voter');
    skipOnboarding($user, 'voter');
    Voter::factory()->create(['user_id' => $user->id, 'is_verified' => true, 'is_active' => true, 'flagged_for_fraud' => false]);
    return $user;
}

it('renders the study systems page with the Government Directory link and a sample of external resources', function () {
    $user = studySystemsVoter();

    $response = $this->actingAs($user)->get(route('voter.study-systems'))
        ->assertOk()
        ->assertSee('Study Systems')
        ->assertSee('Government Directory')
        ->assertSee('https://graph.civlab.org/us', false)
        ->assertSee('Project Gutenberg')
        ->assertSee('http://gutenberg.org', false)
        ->assertSee('Have I Been Pwned')
        ->assertSee('http://haveibeenpwned.com', false);

    $response->assertSee('target="_blank"', false);
    $response->assertSee('rel="noopener noreferrer"', false);
});

it('requires authentication to view the study systems page', function () {
    $this->get(route('voter.study-systems'))->assertRedirect(route('login'));
});
