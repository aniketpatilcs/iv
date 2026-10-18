<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wires up feature modules in app/Modules/<Module>:
 *  - routes.php is loaded with the "web" middleware group;
 *  - listeners in Listeners/ are discovered by EventServiceProvider.
 *
 * Adding a module therefore needs no change outside its own folder,
 * apart from registering any new morph aliases in AppServiceProvider.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        foreach (glob(app_path('Modules/*/routes.php')) ?: [] as $routes) {
            Route::middleware('web')->group($routes);
        }
    }
}
