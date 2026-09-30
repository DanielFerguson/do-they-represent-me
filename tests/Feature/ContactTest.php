<?php

use App\Enums\ContactTopic;
use App\Models\ContactMessage;
use App\Models\Electorate;
use App\Models\Policy;
use App\Notifications\ContactMessageReceived;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function validMessage(array $overrides = []): array
{
    return [
        'topic' => 'general',
        'name' => 'Sam Nguyen',
        'email' => 'sam@example.com',
        'message' => 'How often is the data updated?',
        ...$overrides,
    ];
}

it('shows the contact form', function () {
    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('Get in touch')
        ->assertSee('name="_token"', escape: false);
});

it('prefills a correction about a published question', function () {
    $policy = Policy::factory()->published()->create(['question' => 'Should trains be free?']);

    $this->get(route('contact', ['topic' => 'correction', 'policy' => $policy->slug]))
        ->assertOk()
        ->assertSee('Should trains be free?')
        ->assertSee('value="correction" checked', escape: false);
});

it('ignores a question that is not published', function () {
    $policy = Policy::factory()->create(['question' => 'A draft question?']);

    $this->get(route('contact', ['policy' => $policy->slug]))->assertOk()->assertDontSee('A draft question?');
});

it('stores the message and emails it to the site contact', function () {
    config(['site.contact_email' => 'owner@example.com']);
    Notification::fake();

    $this->post(route('contact.store'), validMessage())
        ->assertRedirect(route('contact'))
        ->assertSessionHas('sent', 'sam@example.com');

    $message = ContactMessage::query()->sole();
    expect($message->topic)->toBe(ContactTopic::General)
        ->and($message->email)->toBe('sam@example.com')
        ->and($message->handled_at)->toBeNull();

    Notification::assertSentOnDemand(
        ContactMessageReceived::class,
        fn (ContactMessageReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'owner@example.com' && $notification->message->is($message),
    );
});

it('records which question or district a correction is about', function (Closure $subject, string $field, string $context) {
    $slug = $subject();

    $this->post(route('contact.store'), validMessage(['topic' => 'correction', $field => $slug]))->assertRedirect();

    expect(ContactMessage::query()->sole())
        ->context->toBe($context)
        ->context_url->toEndWith($slug);
})->with([
    'a question' => [fn () => Policy::factory()->published()->create(['question' => 'Should trains be free?'])->slug, 'policy', 'Question: Should trains be free?'],
    'a district' => [fn () => Electorate::factory()->create(['name' => 'Albert Park'])->slug, 'district', 'District: Albert Park'],
]);

it('still stores the message when no contact address is configured', function () {
    config(['site.contact_email' => null]);
    Notification::fake();

    $this->post(route('contact.store'), validMessage())->assertRedirect(route('contact'));

    expect(ContactMessage::query()->count())->toBe(1);
    Notification::assertNothingSent();
});

it('keeps the message when the email cannot be sent', function () {
    config(['site.contact_email' => 'owner@example.com']);
    Notification::shouldReceive('route')->andThrow(new RuntimeException('Mail server down'));

    $this->post(route('contact.store'), validMessage())->assertRedirect(route('contact'))->assertSessionHas('sent');

    expect(ContactMessage::query()->count())->toBe(1);
});

it('explains what to fix when the email and message are missing', function () {
    $this->post(route('contact.store'), ['topic' => 'general'])
        ->assertSessionHasErrors([
            'email' => 'Enter an email address so we can reply.',
            'message' => 'Write your message.',
        ]);

    expect(ContactMessage::query()->count())->toBe(0);
});

it('rejects invalid input', function (array $input, string $field, string $error) {
    $this->post(route('contact.store'), validMessage($input))->assertSessionHasErrors([$field => $error]);
})->with([
    'a malformed email' => [['email' => 'not-an-email'], 'email', 'Enter an email address like name@example.com.'],
    'a message that is too short' => [['message' => 'Thanks!'], 'message', 'Write at least 10 characters.'],
    'an unknown topic' => [['topic' => 'sales'], 'topic', 'Choose what your message is about.'],
]);

it('quietly discards messages from bots that fill in the hidden field', function () {
    Notification::fake();

    $this->post(route('contact.store'), validMessage(['website' => 'https://spam.example']))
        ->assertRedirect(route('contact'))
        ->assertSessionHas('sent');

    expect(ContactMessage::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('limits each visitor to five messages an hour', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('contact.store'), validMessage())->assertRedirect();
    }

    $this->post(route('contact.store'), validMessage())->assertTooManyRequests();
});

it('deletes messages after a year', function () {
    $old = ContactMessage::factory()->create(['created_at' => now()->subMonths(13)]);
    $recent = ContactMessage::factory()->create(['created_at' => now()->subMonths(11)]);

    $this->artisan('model:prune', ['--model' => [ContactMessage::class]])->assertSuccessful();

    expect(ContactMessage::query()->pluck('id')->all())->toBe([$recent->id])
        ->and($old->fresh())->toBeNull();
});
