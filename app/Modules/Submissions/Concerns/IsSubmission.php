<?php

namespace App\Modules\Submissions\Concerns;

use App\Models\User;
use App\Modules\Submissions\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared implementation of the Submission contract for Eloquent models.
 *
 * @mixin Model
 */
trait IsSubmission
{
    /**
     * @return BelongsTo<User, covariant Model>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphMany<Attachment>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function reference(): ?string
    {
        return $this->getAttribute($this->referenceColumn());
    }

    public function claimNumber(): ?string
    {
        return $this->getAttribute('claim_number');
    }
}
