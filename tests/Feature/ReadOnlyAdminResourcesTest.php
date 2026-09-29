<?php

use App\Filament\Resources\Divisions\DivisionResource;
use App\Filament\Resources\Divisions\Pages\ViewDivision;
use App\Filament\Resources\Divisions\RelationManagers\PartyPositionsRelationManager;
use App\Filament\Resources\Divisions\RelationManagers\VotesRelationManager;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\MembershipsRelationManager;
use App\Filament\Resources\Members\RelationManagers\PolicyAgreementsRelationManager;
use App\Filament\Resources\Policies\Pages\ViewPolicy;
use App\Filament\Resources\Policies\PolicyResource;
use App\Filament\Resources\Policies\RelationManagers\AgreementsRelationManager;
use App\Filament\Resources\Policies\RelationManagers\PolicyDivisionsRelationManager;
use App\Models\Division;
use App\Models\DivisionPartyPosition;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Policy;
use App\Models\PolicyAgreement;
use App\Models\PolicyDivision;
use App\Models\User;
use App\Models\Vote;
use Livewire\Livewire;

beforeEach(function () {
    config(['admin.emails' => ['curator@example.com']]);
});

function curator(): User
{
    return User::factory()->withAppAuthentication()->create(['email' => 'curator@example.com']);
}

dataset('resources', [
    'policies' => [PolicyResource::class, fn () => PolicyDivision::factory()->create()->policy],
    'divisions' => [DivisionResource::class, fn () => Division::factory()->create()],
    'members' => [MemberResource::class, fn () => Membership::factory()->create()->member],
]);

it('lists and shows records to curators', function (string $resource, Closure $record) {
    $this->actingAs(curator());
    $model = $record();

    $this->get($resource::getUrl('index'))->assertOk();
    $this->get($resource::getUrl('view', ['record' => $model]))->assertOk();
})->with('resources');

it('has no pages for creating or editing records', function (string $resource, Closure $record) {
    $this->actingAs(curator());
    $model = $record();

    $this->get($resource::getUrl('index').'/create')->assertNotFound();
    $this->get($resource::getUrl('index').'/'.$model->getKey().'/edit')->assertNotFound();
    expect($resource::canCreate())->toBeFalse()
        ->and($resource::canEdit($model))->toBeFalse()
        ->and($resource::canDelete($model))->toBeFalse();
})->with('resources');

it('forbids users who are not on the allowlist', function () {
    $this->actingAs(User::factory()->withAppAuthentication()->create(['email' => 'someone@example.com']));

    $this->get(PolicyResource::getUrl('index'))->assertForbidden();
});

it('shows the records related to each page', function (string $manager, string $page, Closure $arrange) {
    $this->actingAs(curator());
    [$owner, $related] = $arrange();

    Livewire::test($manager, ['ownerRecord' => $owner, 'pageClass' => $page])->assertOk()->assertCanSeeTableRecords($related);
})->with([
    'policy links' => [PolicyDivisionsRelationManager::class, ViewPolicy::class, function () {
        $link = PolicyDivision::factory()->create();

        return [$link->policy, [$link]];
    }],
    'policy scores for parties' => [AgreementsRelationManager::class, ViewPolicy::class, function () {
        $agreement = PolicyAgreement::factory()->create();

        return [$agreement->policy, [$agreement]];
    }],
    'division party positions' => [PartyPositionsRelationManager::class, ViewDivision::class, function () {
        $position = DivisionPartyPosition::query()->create(['division_id' => Division::factory()->create()->id, 'party_id' => Membership::factory()->create()->party_id, 'ayes' => 1, 'noes' => 0, 'eligible' => 1, 'position' => 'aye']);

        return [$position->division, [$position]];
    }],
    'division votes' => [VotesRelationManager::class, ViewDivision::class, function () {
        $vote = Vote::factory()->by(Membership::factory()->create())->create();

        return [$vote->division, [$vote]];
    }],
    'member seats' => [MembershipsRelationManager::class, ViewMember::class, function () {
        $membership = Membership::factory()->create();

        return [$membership->member, [$membership]];
    }],
    'member scores' => [PolicyAgreementsRelationManager::class, ViewMember::class, function () {
        $member = Member::factory()->create();
        $agreement = PolicyAgreement::factory()->for(Policy::factory())->for($member, 'subject')->create();

        return [$member, [$agreement]];
    }],
]);
