<?php

namespace App\Modules\Submissions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A file uploaded ahead of submission. Claimed (turned into an Attachment and
 * deleted) when its form is submitted; pruned by `uploads:prune` if abandoned.
 *
 * @property string $id
 * @property int $user_id
 * @property string $kind
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property Carbon $created_at
 */
class Upload extends Model
{
    use HasUlids, Prunable;

    /**
     * Allowed file extensions per kind. Each form field accepts exactly one kind,
     * so a file uploaded for one purpose can't be attached to another.
     */
    public const KINDS = [
        'vehicle_image' => ['jpg', 'jpeg'],
        'pickup_document' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
        'bid_document' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'],
    ];

    /** Largest single file in kilobytes (20 MB, as stated in the forms). */
    public const MAX_KB = 20480;

    /** Unclaimed uploads one user may hold at once, so abandoned forms can't fill the disk. */
    public const MAX_PENDING_PER_USER = 200;

    /** Unclaimed uploads older than this are pruned. */
    public const ABANDONED_AFTER_HOURS = 24;

    protected $fillable = [
        'kind',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    /**
     * @return BelongsTo<User, self>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Uploads never attached to a submission (abandoned forms). Claimed uploads are
     * deleted at submit time, so every row still here after the cut-off is unclaimed.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subHours(self::ABANDONED_AFTER_HOURS));
    }

    /**
     * Remove the stored file before the row is pruned.
     */
    protected function pruning(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }
}
