<?php

namespace App\Providers;

use App\Models\Division;
use App\Models\Member;
use App\Models\Party;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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

        // Enough for a real person to follow up, few enough to blunt spam.
        RateLimiter::for('contact', fn (Request $request): Limit => Limit::perHour(5)->by($request->ip()));

        // The footer's "records up to" date. It is a closure so the error
        // pages, which must render while the database is down, never call it.
        View::composer('components.layouts.public', function (\Illuminate\Contracts\View\View $view): void {
            $view->with('latestSittingDate', function (): ?Carbon {
                $latest = Division::query()->max('sitting_date');

                return $latest === null ? null : Carbon::parse((string) $latest);
            });
        });
    }
}
