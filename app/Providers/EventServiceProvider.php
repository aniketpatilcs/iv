<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * Module listeners are discovered automatically (see below); list only
     * listeners that live outside app/Modules here.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Wire every public handle*() method in app/Modules/<Module>/Listeners to the
     * event it type-hints. Production caches the result with `php artisan event:cache`.
     */
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function discoverEventsWithin(): array
    {
        return glob(app_path('Modules/*/Listeners'), GLOB_ONLYDIR) ?: [];
    }
}
