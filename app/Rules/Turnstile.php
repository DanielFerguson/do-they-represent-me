<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Checks a Cloudflare Turnstile token with Cloudflare. A token that can't be
 * checked, because Cloudflare is unreachable, fails like a bad one: the
 * visitor can send again, and spam never gets through.
 */
class Turnstile implements ValidationRule
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private ?string $remoteIp = null) {}

    /**
     * True when the keys are set. Without them, as in local development and
     * tests, the form has no check.
     */
    public static function isEnabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2048 || ! $this->isAccepted($value)) {
            $fail("We couldn't confirm you're a person. Wait a moment for the check to finish, then send again.");
        }
    }

    private function isAccepted(string $token): bool
    {
        try {
            return Http::asForm()->timeout(5)->post(self::VERIFY_URL, array_filter([
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $this->remoteIp,
            ]))->json('success') === true;
        } catch (ConnectionException) {
            return false;
        }
    }
}
