<?php

namespace App\Modules\Auth\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes sign-in activity to the audit log. Credentials are never recorded;
 * failed attempts keep only the username that was tried.
 */
class LogAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        $this->log('login', 'Signed in', $event->user instanceof Model ? $event->user : null);
    }

    public function handleLogout(Logout $event): void
    {
        $this->log('logout', 'Signed out', $event->user instanceof Model ? $event->user : null);
    }

    public function handleFailed(Failed $event): void
    {
        $this->log('failed', 'Sign-in failed', null, ['username' => $event->credentials['username'] ?? null]);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->log('lockout', 'Locked out after repeated failures', null, ['username' => $event->request->input('username')]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(string $event, string $description, ?Model $user, array $properties = []): void
    {
        $activity = activity('auth')
            ->event($event)
            ->withProperties([...$properties, 'ip' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 255)]);

        if ($user) {
            $activity->causedBy($user)->performedOn($user);
        }

        $activity->log($description);
    }
}
