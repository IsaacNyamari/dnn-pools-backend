<?php

use App\Models\GameRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->freezeTime();
    $this->team = institution();
    $this->user = attendant($this->team);
    $this->token = token_for($this->user);
});

function upload_games(array $records, ?string $token = null): TestResponse
{
    return with_token($token ?? test()->token)->postJson('/api/v1/game-records', ['records' => $records]);
}

test('a record is stored with the tenant, the attendant and the uploader', function () {
    $row = game_row($this->team, $this->user, ['local_timestamp' => now()->utc()->subHour()->format('Y-m-d\TH:i:s').'.123456Z']);

    upload_games([$row])->assertCreated()->assertExactJson(['accepted' => 1, 'duplicates' => 0]);

    $record = GameRecord::sole();
    expect($record->only(['institution_id', 'attendant_id', 'uploaded_by', 'device_id', 'table_id', 'location_id', 'code', 'payment_method']))->toBe([
        'institution_id' => $this->team->id,
        'attendant_id' => $this->user->id,
        'uploaded_by' => $this->user->id,
        'device_id' => '',
        'table_id' => 'T01',
        'location_id' => 'MAIN',
        'code' => 'ABC234',
        'payment_method' => 'Cash',
    ])
        ->and($record->status->value)->toBe('used')
        ->and($record->local_timestamp->format('Y-m-d\TH:i:s.u\Z'))->toBe($row['local_timestamp'])
        ->and($record->created_at->timestamp)->toBe(now()->timestamp);
});

test('the uploader may differ from the attendant', function () {
    $colleague = attendant($this->team);

    upload_games([game_row($this->team, $colleague)])->assertCreated();

    expect(GameRecord::sole()->attendant_id)->toBe($colleague->id)
        ->and(GameRecord::sole()->uploaded_by)->toBe($this->user->id);
});

test('records of a since deactivated attendant are still accepted', function () {
    $former = attendant($this->team, active: false);

    upload_games([game_row($this->team, $former)])->assertCreated();
});

test('the client cannot choose who uploaded', function () {
    $colleague = attendant($this->team);

    upload_games([game_row($this->team, $this->user, ['uploaded_by' => $colleague->id, 'created_at' => '2000-01-01T00:00:00Z'])])->assertCreated();

    expect(GameRecord::sole()->uploaded_by)->toBe($this->user->id)
        ->and(GameRecord::sole()->created_at->year)->not->toBe(2000);
});

test('optional fields have defaults', function () {
    $row = game_row($this->team, $this->user, ['device_id' => '', 'payment_method' => '']);
    unset($row['location_id']);

    upload_games([$row])->assertCreated();

    expect(GameRecord::sole()->location_id)->toBe('MAIN')
        ->and(GameRecord::sole()->device_id)->toBe('')
        ->and(GameRecord::sole()->payment_method)->toBeNull();
});

test('re-sent records are counted as duplicates and never overwrite', function () {
    $row = game_row($this->team, $this->user);

    upload_games([$row])->assertCreated();
    upload_games([
        [...$row, 'status' => 'failed', 'payment_method' => 'M-Pesa'],
        [...$row, 'code' => 'XYZ789'],
    ])->assertCreated()->assertExactJson(['accepted' => 1, 'duplicates' => 1]);

    expect(GameRecord::count())->toBe(2)
        ->and(GameRecord::where('code', 'ABC234')->sole()->status->value)->toBe('used')
        ->and(GameRecord::where('code', 'ABC234')->sole()->payment_method)->toBe('Cash');
});

test('the same instant written two ways is one record, but another instant is not', function () {
    $second = now()->utc()->subHour()->format('Y-m-d\TH:i:s');

    upload_games([
        game_row($this->team, $this->user, ['local_timestamp' => "{$second}Z"]),
        game_row($this->team, $this->user, ['local_timestamp' => "{$second}.000+00:00"]),
        game_row($this->team, $this->user, ['local_timestamp' => "{$second}.001Z"]),
    ])->assertCreated()->assertExactJson(['accepted' => 2, 'duplicates' => 1]);
});

test('two institutions may have identical rows', function () {
    $other = institution();
    attendant($other, $this->user);
    $row = game_row($this->team, $this->user);

    upload_games([$row, [...$row, 'institution_id' => $other->id]])
        ->assertCreated()->assertExactJson(['accepted' => 2, 'duplicates' => 0]);
});

test('a batch for an institution the token user is not in is refused entirely', function (Closure $makeInstitution) {
    $foreign = $makeInstitution($this->user);
    $victim = attendant($foreign);

    upload_games([game_row($this->team, $this->user), game_row($foreign, $victim, ['code' => 'XYZ789'])])
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden_institution');

    expect(GameRecord::count())->toBe(0);
})->with([
    'someone else\'s' => [fn () => institution()],
    'deactivated there' => [fn (User $user) => tap(institution(), fn (Team $team) => attendant($team, $user, active: false))],
    'suspended' => [fn (User $user) => tap(institution(), function (Team $team) use ($user) {
        attendant($team, $user);
        $team->delete();
    })],
    'a personal team' => [fn (User $user) => tap(Team::factory()->personal()->create(), fn (Team $team) => director($team, $user))],
]);

test('an unknown institution is forbidden', function () {
    upload_games([game_row($this->team, $this->user, ['institution_id' => 999999])])
        ->assertForbidden()->assertJsonPath('code', 'forbidden_institution');
});

test('an attendant from another institution makes the whole batch invalid', function () {
    $outsider = attendant(institution());

    upload_games([game_row($this->team, $this->user), game_row($this->team, $outsider, ['code' => 'XYZ789'])])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['records.1.attendant_id'])
        ->assertJsonMissingValidationErrors(['records.0.attendant_id']);

    expect(GameRecord::count())->toBe(0);
});

test('an attendant id is checked against its own row\'s institution', function () {
    $other = institution();
    attendant($other, $this->user);
    $onlyHere = attendant($this->team);

    upload_games([game_row($this->team, $onlyHere), game_row($other, $onlyHere)])
        ->assertStatus(422)->assertJsonValidationErrors(['records.1.attendant_id']);
});

test('one invalid record rejects the whole batch', function (string $field, mixed $value) {
    upload_games([
        game_row($this->team, $this->user),
        game_row($this->team, $this->user, ['code' => 'XYZ789', $field => $value]),
    ])->assertStatus(422)->assertJsonValidationErrors(["records.1.{$field}"]);

    expect(GameRecord::count())->toBe(0);
})->with([
    'code with an excluded letter' => ['code', 'ABCI23'],
    'code with a zero' => ['code', 'ABC023'],
    'lowercase code' => ['code', 'abc234'],
    'short code' => ['code', 'ABC23'],
    'long table id' => ['table_id', 'T12345678'],
    'table id with a space' => ['table_id', 'T 1'],
    'unknown status' => ['status', 'done'],
    'long device id' => ['device_id', str_repeat('d', 65)],
    'long location id' => ['location_id', str_repeat('l', 33)],
    'long payment method' => ['payment_method', str_repeat('p', 33)],
    'missing attendant' => ['attendant_id', null],
    'text institution' => ['institution_id', 'abc'],
    'local time without a zone' => ['local_timestamp', '2026-11-26T14:03:11.000'],
    'offset other than UTC' => ['local_timestamp', '2026-11-26T14:03:11+03:00'],
    'impossible date' => ['local_timestamp', '2026-02-30T10:00:00Z'],
    'not a date' => ['local_timestamp', 'yesterday'],
]);

test('the phone clock may be a year behind or a day ahead, no more', function (string $offset, bool $accepted) {
    $time = now()->utc()->modify($offset)->format('Y-m-d\TH:i:s\Z');

    $response = upload_games([game_row($this->team, $this->user, ['local_timestamp' => $time])]);

    $accepted
        ? $response->assertCreated()
        : $response->assertStatus(422)->assertJsonValidationErrors(['records.0.local_timestamp']);
})->with([
    ['-364 days', true],
    ['-366 days', false],
    ['+23 hours', true],
    ['+25 hours', false],
]);

test('a batch holds one to fifty records', function () {
    $rows = fn (int $count) => array_map(fn (int $i) => game_row($this->team, $this->user, ['table_id' => "T{$i}"]), range(1, $count));

    upload_games([])->assertStatus(422)->assertJsonValidationErrors(['records']);
    upload_games($rows(51))->assertStatus(422)->assertJsonValidationErrors(['records']);
    expect(GameRecord::count())->toBe(0);

    upload_games($rows(50))->assertCreated()->assertExactJson(['accepted' => 50, 'duplicates' => 0]);
});

test('the body must be a list of records', function (mixed $records) {
    with_token($this->token)->postJson('/api/v1/game-records', ['records' => $records])->assertStatus(422);
})->with([null, 'abc', [['a' => ['code' => 'ABC234']]], [['not an object']]]);
