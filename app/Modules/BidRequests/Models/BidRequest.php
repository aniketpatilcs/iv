<?php

namespace App\Modules\BidRequests\Models;

use App\Modules\Submissions\Concerns\IsSubmission;
use App\Modules\Submissions\Contracts\Submission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $bid_number
 * @property string $status
 * @property string $claim_number
 * @property string|null $vin
 * @property string|null $vehicle_description
 * @property string|null $vehicle_state
 * @property string|null $acv
 * @property string|null $damage_amount
 * @property Carbon|null $loss_date
 * @property array<string, mixed> $payload
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BidRequest extends Model implements Submission
{
    use IsSubmission, LogsActivity;

    protected $fillable = [
        'user_id',
        'status',
        'claim_number',
        'vin',
        'vehicle_description',
        'vehicle_state',
        'acv',
        'damage_amount',
        'loss_date',
        'payload',
    ];

    protected $casts = [
        'loss_date' => 'date',
        'acv' => 'decimal:2',
        'damage_amount' => 'decimal:2',
        'payload' => 'array',
    ];

    public function referenceColumn(): string
    {
        return 'bid_number';
    }

    public function referencePrefix(): string
    {
        return 'BID';
    }

    public function submissionLabel(): string
    {
        return 'Bid Request';
    }

    /**
     * Audit the identifying fields only; the full form payload is too large to copy into every log entry.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('bid_requests')
            ->logOnly(['bid_number', 'status', 'claim_number', 'vin', 'vehicle_description', 'vehicle_state', 'acv', 'damage_amount', 'loss_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
