<?php

namespace App\Providers;

use App\Models\Member;
use App\Models\Party;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'member' => Member::class,
            'party' => Party::class,
        ]);

        // Build links from APP_URL, never from the request's Host header, so
        // a forged Host can't poison cached pages and visitors on the
        // laravel.cloud address are linked to the real domain.
        if ($this->app->isProduction()) {
            URL::forceRootUrl((string) config('app.url'));
            URL::forceScheme('https');
        }
    }
}
