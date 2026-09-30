<?php

use App\Domain\Districts\DistrictShareImage;
use App\Models\Electorate;
use App\Models\House;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Party;

/**
 * A region with one district in it, for Assembly and Council seats.
 *
 * @return array{assembly: House, council: House, region: Electorate, district: Electorate}
 */
function cardDistrict(): array
{
    $assembly = House::factory()->create(['slug' => 'assembly']);
    $council = House::factory()->create(['slug' => 'council']);
    $region = Electorate::factory()->region()->create(['house_id' => $council->id, 'name' => 'Southern Metropolitan']);
    $district = Electorate::factory()->inRegion($region)->create(['house_id' => $assembly->id, 'name' => 'Albert Park', 'slug' => 'albert-park']);

    return compact('assembly', 'council', 'region', 'district');
}

function cardSeat(Electorate $electorate, House $house, string $name, string $party = 'Labor', ?string $colour = '#de3533', ?string $endsOn = null): Membership
{
    return Membership::factory()
        ->for(Member::factory()->state(['display_name' => $name, 'last_name' => last(explode(' ', $name))]))
        ->for(Party::factory()->state(['display_name' => $party, 'colour' => $colour]))
        ->between('2022-11-26', $endsOn)
        ->create(['house_id' => $house->id, 'electorate_id' => $electorate->id]);
}

function cardUrl(Electorate $district): string
{
    $images = app(DistrictShareImage::class);

    return $images->url($district, $images->cardFor($district));
}

it('draws a 1200 by 630 PNG for the district at its current address', function () {
    ['assembly' => $assembly, 'council' => $council, 'region' => $region, 'district' => $district] = cardDistrict();
    cardSeat($district, $assembly, 'Nina Taylor');
    cardSeat($region, $council, 'Ryan Batchelor');

    $response = $this->get(cardUrl($district))->assertOk();

    expect(substr($response->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    expect(array_slice(getimagesizefromstring($response->getContent()), 0, 2))->toBe([1200, 630]);
});

it('lets the picture be cached anywhere for a year and sets no cookie', function () {
    ['district' => $district] = cardDistrict();

    $this->get(cardUrl($district))
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertHeaderMissing('Set-Cookie');
});

it('sends an out-of-date address on to the current one, without caching the redirect', function () {
    ['district' => $district] = cardDistrict();

    $this->get(route('districts.share', [$district->slug, str_repeat('0', 16)]))
        ->assertRedirect(cardUrl($district))
        ->assertHeader('Cache-Control', 'no-cache, private');
});

it('has no picture for a district that does not exist, a region, or a malformed hash', function () {
    ['region' => $region, 'district' => $district] = cardDistrict();

    $this->get(route('districts.share', ['nowhere', str_repeat('0', 16)]))->assertNotFound();
    $this->get(route('districts.share', [$region->slug, str_repeat('0', 16)]))->assertNotFound();
    $this->get('/districts/'.$district->slug.'/share-not-a-hash.png')->assertNotFound();
});

it('changes its address whenever anything drawn on the picture changes', function (Closure $change) {
    ['assembly' => $assembly, 'council' => $council, 'region' => $region, 'district' => $district] = cardDistrict();
    $mla = cardSeat($district, $assembly, 'Nina Taylor');
    cardSeat($region, $council, 'Ryan Batchelor');

    $before = cardUrl($district);
    $change($district, $region, $mla, $assembly, $council);

    expect(cardUrl($district->refresh()))->not->toBe($before);
})->with([
    'the MLA is renamed' => [fn ($district, $region, $mla) => $mla->member->update(['display_name' => 'Nina Smith'])],
    'the MLA changes party' => [fn ($district, $region, $mla) => $mla->party->update(['display_name' => 'Independent'])],
    'the party colour changes' => [fn ($district, $region, $mla) => $mla->party->update(['colour' => '#123456'])],
    'the seat falls vacant' => [fn ($district, $region, $mla) => $mla->update(['ends_on' => '2026-01-01'])],
    'an MLC is added' => [fn ($district, $region, $mla, $assembly, $council) => cardSeat($region, $council, 'Katherine Copsey', 'Greens', '#10c25b')],
    'the authorisation statement changes' => [fn () => config(['site.authorisation' => 'Authorised by A. Person, 1 Example Street, Melbourne VIC.'])],
    'the district is renamed' => [fn ($district) => $district->update(['name' => 'Albert Park South'])],
    'the region is renamed' => [fn ($district, $region) => $region->update(['name' => 'Southern Victoria'])],
]);

it('keeps the same address when nothing changes', function () {
    ['assembly' => $assembly, 'district' => $district] = cardDistrict();
    cardSeat($district, $assembly, 'Nina Taylor');

    expect(cardUrl($district))->toBe(cardUrl($district));
});

it('lists the MLC in alphabetical order by surname, and only current members', function () {
    ['assembly' => $assembly, 'council' => $council, 'region' => $region, 'district' => $district] = cardDistrict();
    cardSeat($district, $assembly, 'Nina Taylor');
    cardSeat($region, $council, 'John Berger');
    cardSeat($region, $council, 'Ryan Batchelor');
    cardSeat($region, $council, 'David Davis', 'Liberal', '#1c4f9c');
    cardSeat($region, $council, 'Former Member', endsOn: '2024-01-01');

    $card = app(DistrictShareImage::class)->cardFor($district);

    expect(array_column($card['council'], 'name'))->toBe(['Ryan Batchelor', 'John Berger', 'David Davis']);
    expect($card['member']['name'])->toBe('Nina Taylor');
    expect($card['member']['party'])->toBe('Labor');
    expect($card['member']['colour'])->toBe('#de3533');
});

it('gives a party with no colour the neutral grey', function () {
    ['assembly' => $assembly, 'district' => $district] = cardDistrict();
    cardSeat($district, $assembly, 'Nina Taylor', colour: null);

    expect(app(DistrictShareImage::class)->cardFor($district)['member']['colour'])->toBe('#737373');
});

it('draws a district with a vacant seat, no councillors, or only some', function (int $councillors, bool $vacant) {
    ['assembly' => $assembly, 'council' => $council, 'region' => $region, 'district' => $district] = cardDistrict();

    if (! $vacant) {
        cardSeat($district, $assembly, 'Nina Taylor');
    }

    foreach (range(1, $councillors) as $number) {
        if ($councillors > 0) {
            cardSeat($region, $council, "Member Number{$number}");
        }
    }

    $card = app(DistrictShareImage::class)->cardFor($district);

    expect($card['vacant'])->toBe($vacant)->and($card['council'])->toHaveCount($councillors);

    $response = $this->get(cardUrl($district))->assertOk();
    expect(array_slice(getimagesizefromstring($response->getContent()), 0, 2))->toBe([1200, 630]);
})->with([
    'vacant seat, five MLCs' => [5, true],
    'an MLA and no MLCs' => [0, false],
    'an MLA and two MLCs' => [2, false],
]);

it('draws very long names and party names without failing', function () {
    ['assembly' => $assembly, 'council' => $council, 'region' => $region, 'district' => $district] = cardDistrict();
    $district->update(['name' => 'Mount Waverley Heights and the Surrounding Suburbs']);
    cardSeat($district, $assembly, 'Alexandria Papadopoulos-Livingstone-Featherstonehaugh', 'Shooters, Fishers and Farmers', '#7a5c3e');
    cardSeat($region, $council, 'Bartholomew Wolfeschlegelsteinhausenbergerdorff', 'Legalise Cannabis Party of Victoria', '#2f7d32');

    $this->get(cardUrl($district))->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('draws the party stripe along the bottom of the picture in the site colours', function () {
    ['district' => $district] = cardDistrict();

    $image = imagecreatefromstring($this->get(cardUrl($district))->getContent());
    $colourAt = fn (int $x): string => sprintf('#%06x', imagecolorat($image, $x, 625) & 0xFFFFFF);
    $segment = 1200 / 11;

    expect($colourAt((int) ($segment * 0.5)))->toBe('#8c2c8c')
        ->and($colourAt((int) ($segment * 4.5)))->toBe('#de3533')
        ->and($colourAt((int) ($segment * 10.5)))->toBe('#7a5c3e');
});

it('keeps the stripe colours in step with the site stylesheet', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    foreach (DistrictShareImage::STRIPE as $code => $hex) {
        expect($css)->toContain("--color-party-{$code}: {$hex};");
    }

    expect(array_keys(DistrictShareImage::STRIPE))->toBe(['ajp', 'dlp', 'ffv', 'grn', 'alp', 'lcv', 'lib', 'lbt', 'nat', 'onp', 'sff']);
});

it('puts the picture and a description of it in the district page for link previews', function () {
    ['assembly' => $assembly, 'council' => $council, 'region' => $region, 'district' => $district] = cardDistrict();
    cardSeat($district, $assembly, 'Nina Taylor');
    cardSeat($region, $council, 'Ryan Batchelor');

    $this->get(route('districts.show', $district->slug))
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.cardUrl($district).'">', escape: false)
        ->assertSee('<meta property="og:image:alt" content="Albert Park District: Nina Taylor (Labor), and 1 member of the Legislative Council for Southern Metropolitan Region.">', escape: false);
});

it('describes a district with a vacant seat in its preview', function () {
    ['district' => $district] = cardDistrict();

    $this->get(route('districts.show', $district->slug))
        ->assertSee('og:image:alt" content="Albert Park District: the seat is vacant', escape: false);
});
