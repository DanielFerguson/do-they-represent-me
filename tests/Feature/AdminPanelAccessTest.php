<?php

use App\Models\User;

beforeEach(function () {
    config(['admin.emails' => ['curator@example.com']]);
});

it('redirects guests to the admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('forbids authenticated users who are not on the allowlist', function () {
    $user = User::factory()->create(['email' => 'someone@example.com']);

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('matches the allowlist case-insensitively', function () {
    $user = User::factory()->make(['email' => 'Curator@Example.com']);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
});

it('requires allowlisted curators to set up multi-factor authentication', function () {
    $user = User::factory()->create(['email' => 'curator@example.com']);

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirectContains('multi-factor-authentication');
});
