<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Allowlist
    |--------------------------------------------------------------------------
    |
    | Only users whose email address appears in this comma-separated list may
    | access the Filament curation panel. Anyone else is denied, even with
    | a valid account.
    |
    */

    'emails' => array_values(array_filter(array_map(
        fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),

];
