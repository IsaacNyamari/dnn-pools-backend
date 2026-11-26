<?php

namespace App\Models;

use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A closed shift uploaded by the app. Append-only: rows are never updated.
 *
 * @property int $id
 * @property int $institution_id
 * @property int $attendant_id
 * @property int $uploaded_by
 * @property string $device_id
 * @property string $location_id
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property int $total_cash
 * @property int $total_mpesa
 * @property Carbon $created_at
 * @property-read User $attendant
 * @property-read User $uploader
 */
#[Fillable(['institution_id', 'attendant_id', 'uploaded_by', 'device_id', 'location_id', 'start_time', 'end_time', 'total_cash', 'total_mpesa'])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $dateFormat = GameRecord::STORAGE_FORMAT;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'institution_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function attendant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'total_cash' => 'integer',
            'total_mpesa' => 'integer',
        ];
    }
}
