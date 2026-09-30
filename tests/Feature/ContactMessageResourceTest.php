<?php

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['admin.emails' => ['curator@example.com']]);
});

it('lists only unhandled messages by default', function () {
    $this->actingAs(User::factory()->withAppAuthentication()->create(['email' => 'curator@example.com']));
    $unhandled = ContactMessage::factory()->create();
    $handled = ContactMessage::factory()->handled()->create();

    Livewire::test(ListContactMessages::class)
        ->assertCanSeeTableRecords([$unhandled])
        ->assertCanNotSeeTableRecords([$handled]);
});

it('marks a message as handled', function () {
    $this->actingAs(User::factory()->withAppAuthentication()->create(['email' => 'curator@example.com']));
    $message = ContactMessage::factory()->create();

    Livewire::test(ViewContactMessage::class, ['record' => $message->getRouteKey()])
        ->callAction('markHandled')
        ->assertHasNoActionErrors();

    expect($message->fresh()->handled_at)->not->toBeNull();
});

it('shows messages but has no pages for writing or editing them', function () {
    $this->actingAs(User::factory()->withAppAuthentication()->create(['email' => 'curator@example.com']));
    $message = ContactMessage::factory()->create(['message' => '<script>alert(1)</script> is in the text']);

    $this->get(ContactMessageResource::getUrl('view', ['record' => $message]))
        ->assertOk()
        ->assertSee('is in the text')
        ->assertDontSee('<script>alert(1)</script>', escape: false);
    $this->get(ContactMessageResource::getUrl('index').'/create')->assertNotFound();
    expect(ContactMessageResource::canEdit($message))->toBeFalse();
});

it('forbids users who are not on the allowlist', function () {
    $this->actingAs(User::factory()->withAppAuthentication()->create(['email' => 'someone@example.com']));

    $this->get(ContactMessageResource::getUrl('index'))->assertForbidden();
});
