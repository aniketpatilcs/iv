<?php

namespace App\Modules\Dashboard;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Per-user dashboard totals, cached because counts are the expensive part of the
 * page. Cleared by ForgetDashboardStats whenever the user submits something.
 */
class DashboardStats
{
    private const TTL_MINUTES = 10;

    /**
     * @return array{assignments: int, bidRequests: int}
     */
    public function for(User $user): array
    {
        return Cache::remember($this->key($user), now()->addMinutes(self::TTL_MINUTES), fn () => [
            'assignments' => $user->assignments()->count(),
            'bidRequests' => $user->bidRequests()->count(),
        ]);
    }

    public function forget(User $user): void
    {
        Cache::forget($this->key($user));
    }

    private function key(User $user): string
    {
        return "dashboard:stats:{$user->getKey()}";
    }
}
