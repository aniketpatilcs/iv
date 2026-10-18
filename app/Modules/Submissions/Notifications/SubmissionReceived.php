<?php

namespace App\Modules\Submissions\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued so slow mail delivery never holds up the submit request.
 */
class SubmissionReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(
        public string $kind,
        public string $reference,
        public string $claimNumber,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("IV Auto {$this->kind} {$this->reference} received")
            ->greeting("Hello {$notifiable->name},")
            ->line("We received your {$this->kind} for claim #{$this->claimNumber}.")
            ->line("Reference: {$this->reference}")
            ->line('Keep this reference available when contacting IV Auto.');
    }
}
