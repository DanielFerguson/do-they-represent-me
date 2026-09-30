<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.posthog.key' => 'phc_test',
        'services.posthog.host' => 'https://us.i.posthog.com',
    ]);

    Http::preventStrayRequests();
    Http::fake(['us.i.posthog.com/*' => Http::response('{"status":"Ok"}')]);

    $this->withoutDefer();
});

it('forwards an event to PostHog and answers straight away', function () {
    $response = $this->call('POST', '/ingest/e/?ip=0&compression=gzip-js', [], [], [], [
        'CONTENT_TYPE' => 'text/plain',
        'HTTP_USER_AGENT' => 'TestBrowser/1.0',
    ], '{"event":"start_again"}');

    $response->assertOk()->assertExactJson(['status' => 'Ok']);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://us.i.posthog.com/e/?ip=0&compression=gzip-js'
        && $request->body() === '{"event":"start_again"}'
        && $request->header('Content-Type') === ['text/plain']
        && $request->header('User-Agent') === ['TestBrowser/1.0']);
});

it('forwards the batch and v0 capture paths', function (string $path) {
    $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')
        ->assertOk()->assertExactJson(['status' => 'Ok']);

    Http::assertSentCount(1);
})->with([
    'batch' => '/ingest/batch/',
    'v0 event' => '/ingest/i/v0/e/',
    'event without a trailing slash' => '/ingest/e',
]);

it('never forwards cookies or the visitor address, and never sets a cookie', function () {
    $response = $this->call('POST', '/ingest/e/', [], ['session' => 'secret'], [], [
        'CONTENT_TYPE' => 'text/plain',
        'HTTP_COOKIE' => 'session=secret',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        'REMOTE_ADDR' => '203.0.113.9',
    ], '{}');

    $response->assertOk()->assertExactJson(['status' => 'Ok'])->assertHeaderMissing('Set-Cookie');

    Http::assertSent(fn (Request $request) => ! $request->hasHeader('Cookie')
        && ! $request->hasHeader('X-Forwarded-For')
        && ! str_contains(json_encode($request->headers()), '203.0.113.9'));
});

it('keeps a gzip body and its encoding header intact', function () {
    $body = gzencode('{"event":"start_again"}');

    $this->call('POST', '/ingest/e/?compression=gzip-js', [], [], [], [
        'CONTENT_TYPE' => 'text/plain',
        'HTTP_CONTENT_ENCODING' => 'gzip',
    ], $body)->assertOk()->assertExactJson(['status' => 'Ok']);

    Http::assertSent(fn (Request $request) => $request->body() === $body
        && $request->header('Content-Encoding') === ['gzip']);
});

it('refuses any other path', function (string $path) {
    $this->call('POST', $path, [], [], [], [], '{}')->assertNotFound();

    Http::assertNothingSent();
})->with([
    'flags' => '/ingest/flags/',
    'decide' => '/ingest/decide/',
    'static assets' => '/ingest/static/array.js',
    'path traversal' => '/ingest/e/../flags/',
    'nested below an allowed path' => '/ingest/e/extra',
]);

it('only accepts POST', function () {
    $this->get('/ingest/e/')->assertStatus(405);

    Http::assertNothingSent();
});

it('rejects a body over 64 KB', function () {
    $this->call('POST', '/ingest/e/', [], [], [], ['CONTENT_TYPE' => 'text/plain'], str_repeat('a', 65537))
        ->assertStatus(413);

    Http::assertNothingSent();
});

it('does nothing when analytics is switched off', function () {
    config(['services.posthog.key' => null]);

    $this->call('POST', '/ingest/e/', [], [], [], ['CONTENT_TYPE' => 'text/plain'], '{}')
        ->assertNotFound();

    Http::assertNothingSent();
});

it('still answers when PostHog is unreachable', function () {
    Http::fake(['us.i.posthog.com/*' => Http::failedConnection()]);

    $this->call('POST', '/ingest/e/', [], [], [], ['CONTENT_TYPE' => 'text/plain'], '{}')
        ->assertOk()->assertExactJson(['status' => 'Ok']);
});

it('is throttled as a whole, so a flood cannot run up the bill', function () {
    config(['services.posthog.ingest_per_minute' => 3]);

    foreach (range(1, 3) as $attempt) {
        $this->call('POST', '/ingest/e/', [], [], [], ['CONTENT_TYPE' => 'text/plain'], '{}')->assertOk()->assertExactJson(['status' => 'Ok']);
    }

    $this->call('POST', '/ingest/e/', [], [], [], ['CONTENT_TYPE' => 'text/plain'], '{}')
        ->assertStatus(429);
});
