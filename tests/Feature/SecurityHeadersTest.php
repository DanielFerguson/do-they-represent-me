<?php

use App\Domain\Localities\LocalityDirectory;
use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

const PUBLIC_CSP = "default-src 'self';script-src 'self';style-src 'self';img-src 'self' data:;font-src 'self';connect-src 'self';manifest-src 'self';object-src 'none';base-uri 'self';form-action 'self';frame-ancestors 'none'";

it('sends the strict public Content-Security-Policy, with no nonce, on every public response', function (Closure $path) {
    $this->get($path())->assertHeader('Content-Security-Policy', PUBLIC_CSP);
})->with([
    'a page' => fn () => route('home'),
    'a page that does not exist' => fn () => '/no-such-page',
    'a data file' => fn () => app(LocalityDirectory::class)->url(),
    'an expired preview link' => fn () => URL::temporarySignedRoute('preview.quiz', now()->subMinute(), absolute: false),
]);

it('lets the admin panel run Filament’s inline scripts, but still only from this site', function () {
    $policy = $this->get('/admin/login')->headers->get('Content-Security-Policy');

    expect($policy)->toContain("script-src 'self' 'unsafe-inline' 'unsafe-eval'")
        ->toContain("frame-ancestors 'none'")
        ->not->toContain('ui-avatars.com');
});

it('asks browsers to upgrade insecure requests in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    expect($this->get(route('about'))->headers->get('Content-Security-Policy'))->toEndWith(';upgrade-insecure-requests');
});

it('sends the security headers on every response', function () {
    $response = $this->get(route('home'));

    foreach (SecurityHeaders::HEADERS as $name => $value) {
        $response->assertHeader($name, $value);
    }
});

it('keeps the preview pages’ stricter referrer policy', function () {
    $this->get(URL::temporarySignedRoute('preview.quiz', now()->addDay(), absolute: false))
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('builds links from APP_URL in production, whatever Host header a request sends', function () {
    config(['app.url' => 'https://dotheyrepresentme.com']);
    app()->detectEnvironment(fn (): string => 'production');
    (new AppServiceProvider(app()))->boot();

    $this->get('http://attacker.example/about')
        ->assertSee('href="https://dotheyrepresentme.com/districts"', escape: false)
        ->assertSee('<link rel="canonical" href="https://dotheyrepresentme.com/about">', escape: false)
        ->assertDontSee('attacker.example');
});

it('gives each public page a canonical link and share card, but not previews', function () {
    $this->get(route('methodology'))
        ->assertSee('<link rel="canonical" href="'.route('methodology').'">', escape: false)
        ->assertSee('<meta property="og:image" content="'.asset('images/share-methodology.png').'">', escape: false);

    $this->get(URL::temporarySignedRoute('preview.quiz', now()->addDay(), absolute: false))
        ->assertDontSee('rel="canonical"', escape: false);
});

it('draws admin avatars locally instead of sending names to an avatar service', function () {
    $avatar = (new InitialsAvatarProvider)->get(User::factory()->make(['name' => 'Jo Curator']));

    expect($avatar)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(substr($avatar, strlen('data:image/svg+xml;base64,'))))->toContain('>JC<');
});
