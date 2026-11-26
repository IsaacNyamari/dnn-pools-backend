<?php

namespace App\Http\Requests\Api;

use App\Rules\UtcTimestamp;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

class StoreShiftsRequest extends UploadBatchRequest
{
    public const MAX_HOURS = 48;

    public function batchKey(): string
    {
        return 'shifts';
    }

    protected function rowRules(): array
    {
        return [
            'start_time' => ['bail', 'required', new UtcTimestamp],
            'end_time' => ['bail', 'required', new UtcTimestamp],
            'total_cash' => ['required', 'integer', 'min:0', 'max:9999999'],
            'total_mpesa' => ['required', 'integer', 'min:0', 'max:9999999'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $shifts = $this->input('shifts');

            if (! is_array($shifts) || count($shifts) > self::MAX_ROWS) {
                return;
            }

            foreach ($shifts as $index => $shift) {
                $start = is_array($shift) ? ($shift['start_time'] ?? null) : null;
                $end = is_array($shift) ? ($shift['end_time'] ?? null) : null;

                if (! UtcTimestamp::isValid($start) || ! UtcTimestamp::isValid($end)) {
                    continue;
                }

                $start = CarbonImmutable::parse($start);
                $end = CarbonImmutable::parse($end);

                if ($end->lt($start) || $end->gt($start->addHours(self::MAX_HOURS))) {
                    $validator->errors()->add(
                        "shifts.{$index}.end_time",
                        'The end time must not be before the start time or more than '.self::MAX_HOURS.' hours after it.',
                    );
                }
            }
        }];
    }

    protected function columns(array $row): array
    {
        return [
            'start_time' => $this->toStorage($row['start_time']),
            'end_time' => $this->toStorage($row['end_time']),
            'total_cash' => (int) $row['total_cash'],
            'total_mpesa' => (int) $row['total_mpesa'],
        ];
    }
}
