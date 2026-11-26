<?php

namespace App\Http\Requests\Api;

use App\Models\GameRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A batch of 1 to 50 rows uploaded by the app. Tenant authorization happens afterwards,
 * in App\Actions\Api\StoreUploadBatch, once the shape is known to be valid.
 */
abstract class UploadBatchRequest extends FormRequest
{
    public const MAX_ROWS = 50;

    /**
     * The key of the request body that holds the rows.
     */
    abstract public function batchKey(): string;

    /**
     * Validation rules of the fields particular to this kind of row.
     *
     * @return array<string, array<int, mixed>>
     */
    abstract protected function rowRules(): array;

    /**
     * Turn the particular fields of one validated row into database columns.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    abstract protected function columns(array $row): array;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $key = $this->batchKey();

        $rules = [
            $key => ['bail', 'required', 'array', 'list', 'min:1', 'max:'.self::MAX_ROWS],
            "{$key}.*" => ['required', 'array'],
            "{$key}.*.institution_id" => ['required', 'integer', 'min:1'],
            "{$key}.*.attendant_id" => ['required', 'integer', 'min:1'],
            "{$key}.*.device_id" => ['nullable', 'string', 'max:64'],
            "{$key}.*.location_id" => ['nullable', 'string', 'max:32'],
        ];

        foreach ($this->rowRules() as $field => $fieldRules) {
            $rules["{$key}.*.{$field}"] = $fieldRules;
        }

        return $rules;
    }

    /**
     * The validated rows as database columns, in request order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return array_map(fn (array $row) => [
            'institution_id' => (int) $row['institution_id'],
            'attendant_id' => (int) $row['attendant_id'],
            // The app may send these empty (which arrives as null) or leave them out.
            'device_id' => (string) ($row['device_id'] ?? ''),
            'location_id' => (string) ($row['location_id'] ?? 'MAIN'),
            ...$this->columns($row),
        ], array_values($this->validated()[$this->batchKey()]));
    }

    /**
     * Normalise a validated UTC timestamp to the stored format.
     */
    protected function toStorage(string $timestamp): string
    {
        return CarbonImmutable::parse($timestamp)->utc()->format(GameRecord::STORAGE_FORMAT);
    }
}
