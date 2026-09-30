<?php

it('has an invitation banner on the quiz, hidden until the page finds an invitation', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('x-show="hasInvite"', escape: false)
        ->assertSee('x-cloak', escape: false)
        ->assertSee('x-on:click="dismissInvite"', escape: false);
});

it('has a banner for results someone else shared, with a way to take the quiz and compare', function () {
    $this->get(route('results'))
        ->assertOk()
        ->assertSee('x-show="isShared && showsResults"', escape: false)
        ->assertSee('Shared with you')
        ->assertSee('Take the quiz and compare')
        ->assertSee('x-bind:href="compareInviteUrl"', escape: false);
});

it('has a comparison section with the friend and the parties side by side, never hiding any party', function () {
    $this->get(route('results'))
        ->assertOk()
        ->assertSee('x-show="hasCompare"', escape: false)
        ->assertSee('Where you differ')
        ->assertSee('How each of you matched the parties')
        ->assertSee('Every bar is drawn the same way.')
        ->assertDontSee('Show more');
});

it('stops offering to share or change answers on results that belong to someone else', function () {
    $page = $this->get(route('results'))->assertOk()->getContent();

    expect($page)
        ->toContain('x-show="!isShared" x-on:click="openShare"')
        ->toContain('x-show="!isShared" x-bind:href="changeAnswersUrl"')
        ->toContain('x-show="isShared" x-cloak x-on:click="copyLink"');
});

it('never puts a friend into the page on the server, since a friend only exists in the link', function () {
    $this->get(route('results').'?f=1a.2d&n=Zebediah')
        ->assertOk()
        ->assertDontSee('Zebediah')
        ->assertHeaderMissing('Set-Cookie');
});
