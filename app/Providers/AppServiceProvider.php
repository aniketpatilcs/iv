<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Assignments\Models\Assignment;
use App\Modules\BidRequests\Models\BidRequest;
use App\Modules\Submissions\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Outside production, fail loudly on N+1 lazy loading and silently dropped
        // attributes so query problems are caught before they reach real traffic.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Short, stable names stored in polymorphic columns (attachments, activity_log)
        // instead of PHP class names, so classes can move without breaking saved rows.
        // Every model used in a morph relation must be listed; add new modules here.
        Relation::enforceMorphMap([
            'user' => User::class,
            'assignment' => Assignment::class,
            'bid_request' => BidRequest::class,
            'attachment' => Attachment::class,
        ]);
    }
}
