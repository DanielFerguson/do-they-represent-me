<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contact and authorisation
    |--------------------------------------------------------------------------
    |
    | Set in the environment so they are never committed to the public
    | repository. The authorisation statement appears in every page footer
    | once it is set; election material must carry it before launch.
    |
    */

    'contact_email' => env('SITE_CONTACT_EMAIL'),

    'authorisation' => env('SITE_AUTHORISATION'),

    /*
    |--------------------------------------------------------------------------
    | Beta
    |--------------------------------------------------------------------------
    |
    | While true, the quiz and results say the site is in beta and invite
    | corrections, once real questions are published. Set SITE_BETA=false
    | on launch day.
    |
    */

    'beta' => (bool) env('SITE_BETA', true),

    'repository_url' => 'https://github.com/DanielFerguson/do-they-represent-me',

    /*
    |--------------------------------------------------------------------------
    | The Victorian Electoral Commission's electorate lookup
    |--------------------------------------------------------------------------
    |
    | Linked from the suburb finder when a suburb spans more than one district,
    | so voters can check their exact address.
    |
    */

    'vec_lookup_url' => 'https://www.vec.vic.gov.au/electoral-boundaries/which-boundaries-cover-where-i-live',

];
