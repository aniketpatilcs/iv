<?php

namespace App\Modules\Dashboard\Listeners;

use App\Modules\Dashboard\DashboardStats;
use App\Modules\Submissions\Events\SubmissionCreated;

class ForgetDashboardStats
{
    public function __construct(private readonly DashboardStats $stats) {}

    public function handle(SubmissionCreated $event): void
    {
        $this->stats->forget($event->user);
    }
}
