<?php

namespace App\Modules\Submissions\Events;

use App\Models\User;
use App\Modules\Submissions\Contracts\Submission;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a submission and its files are committed. Other modules listen
 * for this instead of being called directly, so they stay independent.
 */
class SubmissionCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Submission $submission,
        public readonly User $user,
    ) {}
}
