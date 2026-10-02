<?php

use App\Enums\ContactTopic;
use App\Models\ContactMessage;
use App\Models\Electorate;
use App\Models\Policy;
use App\Notifications\ContactMessageReceived;
use App\Rules\Turnstile;
use App\Support\Csp\ContactPolicy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
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

function enableTurnstile(): void
{
    config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret_key' => 'secret-key']);
    Http::preventStrayRequests();
}

it('shows the spam check, and lets Cloudflare load it on this page only, when the keys are set', function () {
    enableTurnstile();

    $response = $this->get(route('contact'))->assertOk()->assertSee('data-sitekey="site-key"', escape: false);

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain('script-src \'self\' '.ContactPolicy::TURNSTILE_ORIGIN)
        ->toContain('frame-src '.ContactPolicy::TURNSTILE_ORIGIN)
        ->and($this->get(route('about'))->headers->get('Content-Security-Policy'))->not->toContain(ContactPolicy::TURNSTILE_ORIGIN);
});

it('leaves out the spam check when the keys are not set', function () {
    config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);

    $this->get(route('contact'))->assertOk()->assertDontSee('cf-turnstile');
});

it('stores the message when Cloudflare accepts the spam check', function () {
    enableTurnstile();
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);

    $this->post(route('contact.store'), validMessage(['turnstile' => 'good-token']), ['REMOTE_ADDR' => '203.0.113.9'])
        ->assertRedirect(route('contact'))
        ->assertSessionHasNoErrors();

    expect(ContactMessage::query()->count())->toBe(1);
    Http::assertSent(fn (Request $request): bool => $request['secret'] === 'secret-key' && $request['response'] === 'good-token' && $request['remoteip'] === '203.0.113.9');
});

it('rejects the message when the spam check fails or is missing', function (?string $token, Closure $cloudflare) {
    enableTurnstile();
    $cloudflare();

    $this->post(route('contact.store'), validMessage(['turnstile' => $token]))
        ->assertSessionHasErrors(['turnstile' => "We couldn't confirm you're a person. Wait a moment for the check to finish, then send again."]);

    expect(ContactMessage::query()->count())->toBe(0);
})->with([
    'a token Cloudflare rejects' => ['bad-token', fn () => Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])])],
    'Cloudflare is unreachable' => ['good-token', fn () => Http::fake([Turnstile::VERIFY_URL => fn () => throw new ConnectionException('timed out')])],
    'no token' => [null, fn () => null],
]);
