<?php

use App\Http\Middleware\InjectCookieConsent;

// ---------------------------------------------------------------------------
// InjectCookieConsent appends a framework-free cookie notice before </body>
// on HTML GET responses, and suppresses it once the visitor has acknowledged
// it (cookie: u9_cookie_consent=accepted).
// ---------------------------------------------------------------------------

test('the cookie notice is injected on a first visit', function () {
    $response = $this->get('/privacy-policy');

    $response->assertOk();
    $response->assertSee('id="u9-cookie-consent"', false);
    $response->assertSee('We do not sell or share your personal data', false);
});

test('the cookie notice is suppressed once acknowledged', function () {
    $response = $this->withUnencryptedCookie(InjectCookieConsent::COOKIE_NAME, 'accepted')
        ->get('/privacy-policy');

    $response->assertOk();
    $response->assertDontSee('id="u9-cookie-consent"', false);
});

test('the cookie notice is not injected into JSON responses', function () {
    $response = $this->getJson('/privacy-policy');

    $response->assertDontSee('u9-cookie-consent', false);
});
