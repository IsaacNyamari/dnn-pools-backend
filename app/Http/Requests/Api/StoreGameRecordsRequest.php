<?php

namespace App\Http\Requests\Api;

use App\Enums\GameStatus;
use App\Rules\UtcTimestamp;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Validation\Rule;

class StoreGameRecordsRequest extends UploadBatchRequest
{
    public function batchKey(): string
    {
        return 'records';
    }

    protected function rowRules(): array
    {
        return [
            'table_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,8}$/D'],
            'code' => ['required', 'string', 'regex:/^[A-HJKMNP-Z2-9]{6}$/D'],
            'status' => ['required', 'string', Rule::enum(GameStatus::class)],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'local_timestamp' => ['bail', 'required', new UtcTimestamp, $this->withinClockWindow(...)],
        ];
    }

    /**
     * A phone clock may be wrong, but not wildly.
     */
    private function withinClockWindow(string $attribute, mixed $value, Closure $fail): void
    {
        $time = CarbonImmutable::parse($value);

        if ($time->lt(now()->subDays(365)) || $time->gt(now()->addDay())) {
            $fail('The :attribute must be within 365 days back and 1 day ahead of the server time.');
        }
    }

    protected function columns(array $row): array
    {
        return [
            'table_id' => $row['table_id'],
            'code' => $row['code'],
            'status' => $row['status'],
            'payment_method' => $row['payment_method'] ?? null,
            'local_timestamp' => $this->toStorage($row['local_timestamp']),
        ];
    }
}
