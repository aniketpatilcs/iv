<?php

namespace App\Modules\Assignments\Models;

use App\Modules\Submissions\Concerns\IsSubmission;
use App\Modules\Submissions\Contracts\Submission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $stock_number
 * @property string $type
 * @property string $status
 * @property string $claim_number
 * @property string|null $vin
 * @property string|null $owner_name
 * @property string|null $vehicle_description
 * @property string|null $pickup_state
 * @property Carbon|null $loss_date
 * @property array<string, mixed> $payload
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Assignment extends Model implements Submission
{
    use IsSubmission, LogsActivity;

    public const TYPE_STANDARD = 'standard';

    public const TYPE_TRANSPORT = 'transport';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'claim_number',
        'vin',
        'owner_name',
        'vehicle_description',
        'pickup_state',
        'loss_date',
        'payload',
    ];

    protected $casts = [
        'loss_date' => 'date',
        'payload' => 'array',
    ];

    public function referenceColumn(): string
    {
        return 'stock_number';
    }

    public function referencePrefix(): string
    {
        return 'IV';
    }

    public function submissionLabel(): string
    {
        return 'Assignment';
    }

    /**
     * Audit the identifying fields only; the full form payload is too large to copy into every log entry.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('assignments')
            ->logOnly(['stock_number', 'type', 'status', 'claim_number', 'vin', 'owner_name', 'vehicle_description', 'pickup_state', 'loss_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
