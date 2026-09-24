<?php

it('renders the contact page linking to the configured contact form', function (): void {
    config(['u9itus.contact_form_url' => 'https://forms.example.test/contact']);

    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('Contact us')
        ->assertSee('https://forms.example.test/contact', false);
});

it('no longer accepts contact posts that were silently discarded', function (): void {
    $this->post('/contact', [
        'name' => 'A', 'email' => 'a@example.test', 'subject' => 'Hi', 'message' => 'Hello',
    ])->assertStatus(405);
});
