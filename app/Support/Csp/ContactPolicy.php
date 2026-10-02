<?php

namespace App\Support\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

/**
 * The public policy, plus Cloudflare Turnstile, which the contact form uses
 * to stop spam. Its script loads a challenge in a frame, both from Cloudflare.
 * No other page loads anything from anyone else.
 */
class ContactPolicy implements Preset
{
    public const TURNSTILE_ORIGIN = 'https://challenges.cloudflare.com';

    public function configure(Policy $policy): void
    {
        (new PublicPolicy)->configure($policy);

        $policy
            ->add(Directive::SCRIPT, self::TURNSTILE_ORIGIN)
            ->add(Directive::FRAME, self::TURNSTILE_ORIGIN);
    }
}
