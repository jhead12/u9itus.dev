<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Skip onboarding for a test user
 * This prevents onboarding middleware from redirecting during tests
 */
function skipOnboarding(\App\Models\User $user, ?string $userType = null): void
{
    // Auto-detect user type if not provided
    if (!$userType) {
        if ($user->hasRole('voter')) {
            $userType = 'voter';
        } elseif ($user->hasRole('politician')) {
            $userType = 'politician';
        } elseif ($user->hasRole('admin')) {
            $userType = 'admin';
        } else {
            $userType = 'voter'; // default
        }
    }

    \App\Models\OnboardingProgress::updateOrCreate(
        ['user_id' => $user->id],
        [
            'user_type' => $userType,
            'current_phase' => 'welcome',
            'completed_phases' => [],
            'is_completed' => true,
            'skipped' => true,
            'completed_at' => now(),
        ]
    );
}

function something()
{
    // ..
}
