<?php

use App\Models\Shift;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->team = institution();
    $this->user = attendant($this->team);
    $this->token = token_for($this->user);
});

function upload_shifts(array $shifts): TestResponse
{
    return with_token(test()->token)->postJson('/api/v1/shifts', ['shifts' => $shifts]);
}

test('a shift is stored once and re-sending it changes nothing', function () {
    $colleague = attendant($this->team);
    $row = shift_row($this->team, $colleague);

    upload_shifts([$row])->assertCreated()->assertExactJson(['accepted' => 1, 'duplicates' => 0]);
    upload_shifts([[...$row, 'total_cash' => 99]])->assertCreated()->assertExactJson(['accepted' => 0, 'duplicates' => 1]);

    $shift = Shift::sole();
    expect($shift->only(['institution_id', 'attendant_id', 'uploaded_by', 'total_cash', 'total_mpesa']))->toBe([
        'institution_id' => $this->team->id,
        'attendant_id' => $colleague->id,
        'uploaded_by' => $this->user->id,
        'total_cash' => 12,
        'total_mpesa' => 7,
    ])
        ->and($shift->start_time->toIso8601String())->toBe('2026-11-26T06:00:00+00:00')
        ->and($shift->end_time->toIso8601String())->toBe('2026-11-26T14:00:00+00:00');
});

test('another device or attendant is another shift', function () {
    $colleague = attendant($this->team);

    upload_shifts([
        shift_row($this->team, $this->user),
        shift_row($this->team, $this->user, ['device_id' => 'phone-2']),
        shift_row($this->team, $colleague),
    ])->assertCreated()->assertExactJson(['accepted' => 3, 'duplicates' => 0]);
});

test('shifts follow the same tenant rules', function () {
    $foreign = institution();
    $outsider = attendant($foreign);

    upload_shifts([shift_row($this->team, $this->user), shift_row($foreign, $outsider)])
        ->assertForbidden()->assertJsonPath('code', 'forbidden_institution');
    upload_shifts([shift_row($this->team, $this->user), shift_row($this->team, $outsider)])
        ->assertStatus(422)->assertJsonValidationErrors(['shifts.1.attendant_id']);

    expect(Shift::count())->toBe(0);
});

test('boundary values are accepted', function () {
    upload_shifts([
        shift_row($this->team, $this->user, ['end_time' => '2026-11-26T06:00:00.000Z', 'total_cash' => 0, 'total_mpesa' => 9999999]),
        shift_row($this->team, $this->user, ['device_id' => 'long', 'end_time' => '2026-11-28T06:00:00.000Z']),
    ])->assertCreated();
});

test('one invalid shift rejects the whole batch', function (array $change, string $field) {
    upload_shifts([
        shift_row($this->team, $this->user),
        shift_row($this->team, $this->user, ['device_id' => 'other', ...$change]),
    ])->assertStatus(422)->assertJsonValidationErrors(["shifts.1.{$field}"]);

    expect(Shift::count())->toBe(0);
})->with([
    'ends before it starts' => [['end_time' => '2026-11-26T05:59:59.000Z'], 'end_time'],
    'longer than 48 hours' => [['end_time' => '2026-11-28T06:00:01.000Z'], 'end_time'],
    'still open' => [['end_time' => ''], 'end_time'],
    'bad start' => [['start_time' => 'soon'], 'start_time'],
    'negative cash' => [['total_cash' => -1], 'total_cash'],
    'too much mpesa' => [['total_mpesa' => 10000000], 'total_mpesa'],
    'fraction' => [['total_cash' => 1.5], 'total_cash'],
    'missing total' => [['total_mpesa' => null], 'total_mpesa'],
]);

test('a batch holds at most fifty shifts', function () {
    upload_shifts(array_map(fn (int $i) => shift_row($this->team, $this->user, ['device_id' => "d{$i}"]), range(1, 51)))
        ->assertStatus(422)->assertJsonValidationErrors(['shifts']);
});
