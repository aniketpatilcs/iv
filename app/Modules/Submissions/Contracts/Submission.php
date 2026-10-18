<?php

namespace App\Modules\Submissions\Contracts;

use App\Models\User;
use App\Modules\Submissions\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A user-submitted record (assignment, bid request, …) that gets a public
 * reference number and may carry uploaded files. Implement this on a new
 * module's model (with the IsSubmission trait) to reuse SubmissionService,
 * attachments and the receipt email.
 */
interface Submission
{
    /** Column holding the public reference, e.g. "stock_number". */
    public function referenceColumn(): string;

    /** Prefix for the public reference, e.g. "IV" → IV0000123. */
    public function referencePrefix(): string;

    /** Human label used in emails and logs, e.g. "Bid Request". */
    public function submissionLabel(): string;

    /** The public reference, once assigned. */
    public function reference(): ?string;

    /** The insurance claim the submission belongs to. */
    public function claimNumber(): ?string;

    /**
     * @return BelongsTo<User, covariant Model>
     */
    public function user(): BelongsTo;

    /**
     * @return MorphMany<Attachment>
     */
    public function attachments(): MorphMany;
}
