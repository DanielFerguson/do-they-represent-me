<?php

namespace App\Support\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;
use Spatie\Csp\Value;

/**
 * The Content-Security-Policy for the public site: everything from our own
 * origin, nothing inline and nothing from anyone else.
 *
 * It has no nonces, so pages stay identical for every visitor and can be
 * cached. Alpine's CSP build needs no eval, and style bindings set styles
 * through the CSSOM, which style-src does not restrict (keep them objects,
 * never strings).
 */
class PublicPolicy implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::DEFAULT, Keyword::SELF)
            ->add(Directive::SCRIPT, Keyword::SELF)
            ->add(Directive::STYLE, Keyword::SELF)
            ->add(Directive::IMG, [Keyword::SELF, 'data:'])
            ->add(Directive::FONT, Keyword::SELF)
            ->add(Directive::CONNECT, Keyword::SELF)
            ->add(Directive::MANIFEST, Keyword::SELF)
            ->add(Directive::OBJECT, Keyword::NONE)
            ->add(Directive::BASE, Keyword::SELF)
            ->add(Directive::FORM_ACTION, Keyword::SELF)
            ->add(Directive::FRAME_ANCESTORS, Keyword::NONE);

        if (app()->isProduction()) {
            $policy->add(Directive::UPGRADE_INSECURE_REQUESTS, Value::NO_VALUE);
        }
    }
}
