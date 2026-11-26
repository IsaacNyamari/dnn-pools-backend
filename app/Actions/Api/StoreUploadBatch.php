<?php

namespace App\Actions\Api;

use App\Exceptions\ApiException;
use App\Http\Requests\Api\UploadBatchRequest;
use App\Models\GameRecord;
use App\Models\Membership;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreUploadBatch
{
    /**
     * Authorize and store a batch: all rows or none. Rows already stored (by the unique
     * key of the table) are counted as duplicates and left untouched.
     *
     * @param  class-string<GameRecord|Shift>  $model
     * @return array{accepted: int, duplicates: int}
     */
    public function handle(User $uploader, UploadBatchRequest $request, string $model): array
    {
        $rows = $request->rows();

        $this->authorizeInstitutions($uploader, $rows);
        $this->ensureAttendantsBelong($request->batchKey(), $rows);

        $createdAt = now()->utc()->format(GameRecord::STORAGE_FORMAT);

        $rows = array_map(fn (array $row) => [
            ...$row,
            'uploaded_by' => $uploader->id,
            'created_at' => $createdAt,
        ], $rows);

        // Every row is already validated, so the only thing "ignore" skips is a duplicate.
        $accepted = DB::transaction(fn () => $model::query()->insertOrIgnore($rows));

        return ['accepted' => $accepted, 'duplicates' => count($rows) - $accepted];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function authorizeInstitutions(User $uploader, array $rows): void
    {
        $requested = array_unique(array_column($rows, 'institution_id'));

        $allowed = $uploader->activeInstitutionMemberships()
            ->whereIn('team_id', $requested)
            ->pluck('team_id')
            ->all();

        if (array_diff($requested, $allowed) !== []) {
            throw ApiException::forbiddenInstitution();
        }
    }

    /**
     * An attendant may have been deactivated since the game was played, so inactive
     * memberships count.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function ensureAttendantsBelong(string $batchKey, array $rows): void
    {
        $memberships = Membership::query()
            ->whereIn('team_id', array_unique(array_column($rows, 'institution_id')))
            ->whereIn('user_id', array_unique(array_column($rows, 'attendant_id')))
            ->get(['team_id', 'user_id'])
            ->mapWithKeys(fn (Membership $membership) => ["{$membership->team_id}:{$membership->user_id}" => true]);

        $errors = [];

        foreach ($rows as $index => $row) {
            if (! $memberships->has("{$row['institution_id']}:{$row['attendant_id']}")) {
                $errors["{$batchKey}.{$index}.attendant_id"] = ['This attendant does not belong to the institution.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
