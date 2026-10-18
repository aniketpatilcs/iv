<?php

namespace App\Modules\Submissions\Listeners;

use App\Modules\Submissions\Events\SubmissionCreated;
use App\Modules\Submissions\Notifications\SubmissionReceived;

class SendSubmissionReceipt
{
    public function handle(SubmissionCreated $event): void
    {
        $event->user->notify(new SubmissionReceived(
            $event->submission->submissionLabel(),
            (string) $event->submission->reference(),
            (string) $event->submission->claimNumber(),
        ));
    }
}
