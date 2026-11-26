<?php

namespace App\Models;

use App\Enums\GameStatus;
use Database\Factories\GameRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One game started from the app. Append-only: rows are never updated.
 *
 * @property int $id
 * @property int $institution_id
 * @property int $attendant_id
 * @property int $uploaded_by
 * @property string $device_id
 * @property string $table_id
 * @property string $location_id
 * @property string $code
 * @property string|null $payment_method
 * @property GameStatus $status
 * @property Carbon $local_timestamp
 * @property Carbon $created_at
 * @property-read Team $institution
 * @property-read User $attendant
 * @property-read User $uploader
 */
#[Fillable(['institution_id', 'attendant_id', 'uploaded_by', 'device_id', 'table_id', 'location_id', 'code', 'payment_method', 'status', 'local_timestamp'])]
class GameRecord extends Model
{
    /** @use HasFactory<GameRecordFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Microseconds are kept: they are part of the idempotency key.
     */
    public const STORAGE_FORMAT = 'Y-m-d H:i:s.u';

    protected $dateFormat = self::STORAGE_FORMAT;

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
            'status' => GameStatus::class,
            'local_timestamp' => 'datetime',
        ];
    }
}
