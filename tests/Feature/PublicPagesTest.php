<?php

use App\Domain\Stances\StanceSnapshots;
use App\Models\Policy;

it('renders the public pages', function (string $route, string $text) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee($text, escape: false)
        ->assertSee('Your answers stay in your browser');
})->with([
    'home' => ['home', 'How do Victoria\'s parties actually vote?'],
    'quiz' => ['quiz', 'Loading questions'],
    'results' => ['results', 'Working out your results'],
]);

it('marks the prototype data as sample data', function (string $route) {
    $this->get(route($route))->assertSee('Prototype.');
})->with(['home', 'quiz', 'results']);

it('publishes sample stances with stable policy ids and a stance for every party', function () {
    $data = json_decode(file_get_contents(public_path('stances/sample.json')), true, flags: JSON_THROW_ON_ERROR);
    $partyCodes = array_column($data['parties'], 'code');
    $ids = array_column($data['policies'], 'id');

    expect($data['sample'])->toBeTrue()
        ->and($ids)->toBe(array_values(array_unique($ids)))
        ->and($ids)->each->toBeInt();

    foreach ($data['policies'] as $policy) {
        expect(array_keys($policy['stances']))->toBe($partyCodes);

        foreach ($policy['stances'] as $stance) {
            expect($stance['agreement'] === null || ($stance['agreement'] >= 0 && $stance['agreement'] <= 1))->toBeTrue();
        }
    }
});

it('points the quiz and results at the sample data while no policy is published', function (string $route) {
    $this->get(route($route))->assertSee('data-stances-url="'.asset('stances/sample.json').'"', escape: false);
})->with(['quiz', 'results']);

it('points the quiz and results at the published data once a policy is published', function (string $route) {
    Policy::factory()->published()->create();
    $snapshot = app(StanceSnapshots::class)->publish();

    $this->get(route($route))->assertSee('data-stances-url="'.route('stances.show', $snapshot->hash).'"', escape: false);
})->with(['quiz', 'results']);

it('drops the prototype notice once a policy is published', function (string $route) {
    Policy::factory()->published()->create();
    app(StanceSnapshots::class)->publish();

    $this->get(route($route))->assertDontSee('Prototype.');
})->with(['home', 'quiz', 'results']);
