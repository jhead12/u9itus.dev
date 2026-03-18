<?php

test('confirm-password screen is unavailable in standalone auth', function () {
    $this->get('/confirm-password')->assertNotFound();
});

test('confirm-password submission is unavailable in standalone auth', function () {
    $this->post('/confirm-password', [
        'password' => 'password',
    ])->assertNotFound();
});
