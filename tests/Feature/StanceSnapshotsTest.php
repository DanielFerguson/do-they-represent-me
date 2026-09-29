<?php

use App\Domain\Stances\StanceSnapshots;
use App\Enums\PolicyStatus;
use App\Models\Party;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use App\Models\StanceSnapshot;

function publishStances(): ?StanceSnapshot
{
    return app(StanceSnapshots::class)->publish();
}

it('publishes nothing until a policy is published', function () {
    Policy::factory()->create(['status' => PolicyStatus::Review]);

    expect(publishStances())->toBeNull()
        ->and(app(StanceSnapshots::class)->current())->toBeNull()
        ->and(StanceSnapshot::query()->count())->toBe(0);
});

it('stores the exact JSON body, named by the hash of its content', function () {
    Policy::factory()->published()->create(['number' => 4]);

    $snapshot = publishStances();

    $body = json_decode($snapshot->payload, true);
    $content = $body;
    unset($content['version']);
    expect($body['version'])->toBe($snapshot->hash)
        ->and($snapshot->hash)->toBe(hash('sha256', json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)))
        ->and(array_column($body['policies'], 'id'))->toBe([4]);
});

it('keeps one version while the data is unchanged', function () {
    Policy::factory()->published()->create();

    $first = publishStances();
    $second = publishStances();

    expect($second->hash)->toBe($first->hash)
        ->and(StanceSnapshot::query()->count())->toBe(1);
});

it('makes an earlier version live again when the data changes back', function () {
    $policy = Policy::factory()->published()->create();
    $party = Party::factory()->create();
    $agreement = PolicyAgreement::factory()->for($policy)->for($party, 'subject')->agreement(1.0)->create();
    $original = publishStances();
    $this->travel(1)->minutes();
    $agreement->update(['agreement' => 0.5, 'category' => 'mixture']);
    $changed = publishStances();
    $this->travel(1)->minutes();
    $agreement->update(['agreement' => 1.0, 'category' => 'for3']);

    publishStances();

    expect($changed->hash)->not->toBe($original->hash)
        ->and(app(StanceSnapshots::class)->current()->hash)->toBe($original->hash)
        ->and(StanceSnapshot::query()->count())->toBe(2);
});

it('stops serving published data when every policy is unpublished', function () {
    $policy = Policy::factory()->published()->create();
    publishStances();

    $policy->update(['status' => PolicyStatus::Review]);

    expect(app(StanceSnapshots::class)->current())->toBeNull();
});

it('serves a stored version with year-long caching and no cookies', function () {
    Policy::factory()->published()->create();
    $snapshot = publishStances();

    $this->get(route('stances.show', $snapshot->hash))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertHeaderMissing('Set-Cookie')
        ->assertContent($snapshot->payload);
});

it('returns 404 for a version that does not exist', function () {
    $this->get('/stances/'.str_repeat('0', 64).'.json')->assertNotFound();
});
