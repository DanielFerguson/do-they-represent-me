<?php

namespace App\Support\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;
use Spatie\Csp\Value;

/**
 * The Content-Security-Policy for the admin panel. Filament's layout has
 * inline scripts and styles, and Livewire's standard build evaluates
 * expressions, so scripts and styles may be inline; everything must still
 * come from our own origin. blob: covers the workbook upload preview.
 */
class AdminPolicy implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::DEFAULT, Keyword::SELF)
            ->add(Directive::SCRIPT, [Keyword::SELF, Keyword::UNSAFE_INLINE, Keyword::UNSAFE_EVAL])
            ->add(Directive::STYLE, [Keyword::SELF, Keyword::UNSAFE_INLINE])
            ->add(Directive::IMG, [Keyword::SELF, 'data:', 'blob:'])
            ->add(Directive::FONT, [Keyword::SELF, 'data:'])
            ->add(Directive::CONNECT, Keyword::SELF)
            ->add(Directive::OBJECT, Keyword::NONE)
            ->add(Directive::BASE, Keyword::SELF)
            ->add(Directive::FORM_ACTION, Keyword::SELF)
            ->add(Directive::FRAME_ANCESTORS, Keyword::NONE);

        if (app()->isProduction()) {
            $policy->add(Directive::UPGRADE_INSECURE_REQUESTS, Value::NO_VALUE);
        }
    }
}
