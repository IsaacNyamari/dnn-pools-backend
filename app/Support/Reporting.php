<?php

namespace App\Support;

use App\Models\GameRecord;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared figures of the web dashboards. As in the app, only "used" records are games;
 * "failed" and "expired" are problems, and "sent" never got a confirmation.
 */
class Reporting
{
    private const TOTAL_COLUMNS = [
        'games' => "status = 'used'",
        'cash' => "status = 'used' and lower(payment_method) = 'cash'",
        'mpesa' => "status = 'used' and lower(payment_method) in ('m-pesa', 'mpesa')",
        'failed' => "status = 'failed'",
        'expired' => "status = 'expired'",
        'unconfirmed' => "status = 'sent'",
    ];

    /**
     * Add the total columns to a game record query, for use alone or with groupBy().
     *
     * @param  Builder<GameRecord>  $query
     * @return Builder<GameRecord>
     */
    public static function selectTotals(Builder $query): Builder
    {
        foreach (self::TOTAL_COLUMNS as $name => $condition) {
            $query->selectRaw("coalesce(sum(case when {$condition} then 1 else 0 end), 0) as {$name}");
        }

        return $query;
    }

    /**
     * @param  Builder<GameRecord>  $query
     * @return array{games: int, cash: int, mpesa: int, failed: int, expired: int, unconfirmed: int}
     */
    public static function totals(Builder $query): array
    {
        $row = self::selectTotals(clone $query)->toBase()->first();

        return [
            'games' => (int) ($row->games ?? 0),
            'cash' => (int) ($row->cash ?? 0),
            'mpesa' => (int) ($row->mpesa ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'expired' => (int) ($row->expired ?? 0),
            'unconfirmed' => (int) ($row->unconfirmed ?? 0),
        ];
    }

    /**
     * Restrict a query to whole days in the display time zone. Either end may be empty.
     *
     * @template TModel of GameRecord|Shift
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function between(Builder $query, string $column, ?string $from, ?string $to): Builder
    {
        if ($start = self::day($from)) {
            $query->where($column, '>=', $start->utc());
        }

        if ($end = self::day($to)) {
            $query->where($column, '<', $end->addDay()->utc());
        }

        return $query;
    }

    /**
     * Midnight of a YYYY-MM-DD date in the display time zone, or null when it is not one.
     */
    public static function day(?string $date): ?CarbonImmutable
    {
        if ($date === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $parts) !== 1
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return CarbonImmutable::parse($date, config('pool.timezone'));
    }

    public static function today(): string
    {
        return now()->setTimezone(config('pool.timezone'))->toDateString();
    }

    public static function dateTime(?CarbonInterface $time): string
    {
        return $time ? $time->setTimezone(config('pool.timezone'))->format('d M Y, H:i') : '—';
    }

    /**
     * Seconds between the game on the phone and its arrival here. A phone clock running
     * ahead counts as no delay.
     */
    public static function delaySeconds(GameRecord $record): int
    {
        return (int) max(0, $record->local_timestamp->diffInSeconds($record->created_at, false));
    }

    /**
     * Sync delay of the most recent uploads of the last few days.
     *
     * @param  Builder<GameRecord>  $query
     * @return array{count: int, median: int|null, p95: int|null, max: int|null}
     */
    public static function syncDelay(Builder $query, int $days = 7, int $limit = 5000): array
    {
        $delays = (clone $query)
            ->where('created_at', '>=', now()->subDays($days))
            ->latest('created_at')
            ->limit($limit)
            ->get(['local_timestamp', 'created_at'])
            ->map(fn (GameRecord $record) => self::delaySeconds($record))
            ->sort()
            ->values();

        $count = $delays->count();
        $at = fn (float $quantile) => $count ? $delays[(int) ceil($quantile * $count) - 1] : null;

        return ['count' => $count, 'median' => $at(0.5), 'p95' => $at(0.95), 'max' => $count ? $delays->last() : null];
    }

    public static function duration(?int $seconds): string
    {
        return match (true) {
            $seconds === null => '—',
            $seconds < 60 => $seconds.' s',
            $seconds < 3600 => intdiv($seconds, 60).' min',
            $seconds < 86400 => intdiv($seconds, 3600).' h '.intdiv($seconds % 3600, 60).' min',
            default => intdiv($seconds, 86400).' d '.intdiv($seconds % 86400, 3600).' h',
        };
    }
}
