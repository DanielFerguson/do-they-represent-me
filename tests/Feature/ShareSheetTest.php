<?php

use Illuminate\Support\Facades\URL;

it('offers to share results, with the share sheet and a fallback message', function () {
    $this->get(route('results'))
        ->assertOk()
        ->assertSee('x-on:click="openShare"', escape: false)
        ->assertSee('<dialog', escape: false)
        ->assertSee('What to share')
        ->assertSee('My results')
        ->assertSee('Invite someone')
        ->assertSee('Share a link, or an image of these results. Nothing is sent to us.');
});

it('puts the authorisation statement where the share card can read it, only when one is set', function () {
    config(['site.authorisation' => 'Authorised by A. Person, 1 Example Street, Melbourne VIC.']);

    $this->get(route('results'))
        ->assertSee('data-authorisation="Authorised by A. Person, 1 Example Street, Melbourne VIC."', escape: false);

    config(['site.authorisation' => null]);

    $this->get(route('results'))->assertSee('data-authorisation=""', escape: false);
});

it('keeps preview pages free of the share sheet, because their links are signed and private', function () {
    $this->get(URL::temporarySignedRoute('preview.results', now()->addDay(), absolute: false))
        ->assertOk()
        ->assertDontSee('<dialog', escape: false)
        ->assertDontSee('x-on:click="openShare"', escape: false)
        ->assertSee('Copy a link to these results');
});

it('keeps the results page cacheable and cookie-free', function () {
    $this->get(route('results'))
        ->assertOk()
        ->assertHeaderMissing('Set-Cookie')
        ->assertHeader('Cache-Control', 'max-age=60, public, s-maxage=300, stale-while-revalidate=86400');
});
