<?php

use App\Enums\GameStatus;
use App\Models\GameRecord;
use App\Models\Shift;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    // 12:00 in Nairobi (UTC+3).
    $this->travelTo(CarbonImmutable::parse('2026-11-26 09:00:00', 'UTC'));
    $this->team = institution();
    $this->director = director($this->team);
    $this->jane = attendant($this->team, User::factory()->create(['name' => 'Jane Attendant']));
    $this->omar = attendant($this->team, User::factory()->create(['name' => 'Omar Attendant']));
});

function game(Team $team, User $attendant, array $attributes = []): GameRecord
{
    return GameRecord::factory()->create(['institution_id' => $team->id, 'attendant_id' => $attendant->id, ...$attributes]);
}

function director_page(string $page)
{
    return Livewire::actingAs(test()->director)->test("pages::director.{$page}", ['current_team' => test()->team->slug]);
}

test('totals count used games only, per attendant and per table', function () {
    game($this->team, $this->jane, ['table_id' => 'T01', 'payment_method' => 'Cash']);
    game($this->team, $this->jane, ['table_id' => 'T01', 'payment_method' => 'M-Pesa']);
    game($this->team, $this->jane, ['table_id' => 'T02', 'status' => GameStatus::Failed]);
    game($this->team, $this->omar, ['table_id' => 'T02', 'payment_method' => 'Cash']);
    game($this->team, $this->omar, ['table_id' => 'T02', 'status' => GameStatus::Sent]);
    Shift::factory()->create(['institution_id' => $this->team->id, 'attendant_id' => $this->omar->id, 'total_cash' => 4, 'total_mpesa' => 2]);

    $page = director_page('dashboard');

    expect($page->instance()->periodTotals())->toBe(['games' => 3, 'cash' => 2, 'mpesa' => 1, 'failed' => 1, 'expired' => 0, 'unconfirmed' => 1]);

    $perAttendant = $page->instance()->perAttendant()->keyBy('name');
    expect($perAttendant['Jane Attendant'])->toMatchArray(['games' => 2, 'cash' => 1, 'mpesa' => 1, 'failed' => 1, 'shifts' => 0])
        ->and($perAttendant['Omar Attendant'])->toMatchArray(['games' => 1, 'cash' => 1, 'shifts' => 1, 'shift_cash' => 4, 'shift_mpesa' => 2]);

    expect($page->instance()->perTable()->map(fn ($row) => [$row->table_id, (int) $row->games])->all())
        ->toBe([['T01', 2], ['T02', 1]]);
});

test('another institution\'s data never shows', function () {
    $foreign = institution();
    $stranger = attendant($foreign, User::factory()->create(['name' => 'Foreign Stranger']));
    game($foreign, $stranger, ['code' => 'QQQ999']);
    Shift::factory()->create(['institution_id' => $foreign->id, 'attendant_id' => $stranger->id]);
    game($this->team, $this->jane, ['code' => 'JJJ222']);

    director_page('games')->assertSee('JJJ222')->assertDontSee('QQQ999')->assertDontSee('Foreign Stranger')
        // Asking for a foreign attendant by id must not widen the query.
        ->set('attendant', (string) $stranger->id)->assertDontSee('QQQ999')->assertDontSee('JJJ222');

    director_page('shifts')->assertSee('No shifts found.')->assertDontSee('Foreign Stranger');

    expect(director_page('dashboard')->instance()->periodTotals()['games'])->toBe(1)
        ->and(director_page('dashboard')->instance()->today()['games'])->toBe(1);

    director_page('attendants')->assertDontSee('Foreign Stranger');
});

test('date filters are whole days in the display time zone', function () {
    // 00:30 and 23:30 on 25 November in Nairobi, then 00:00 on the 26th.
    game($this->team, $this->jane, ['code' => 'AAA222', 'local_timestamp' => CarbonImmutable::parse('2026-11-24 21:30:00', 'UTC')]);
    game($this->team, $this->jane, ['code' => 'BBB333', 'local_timestamp' => CarbonImmutable::parse('2026-11-25 20:30:00', 'UTC')]);
    game($this->team, $this->jane, ['code' => 'CCC444', 'local_timestamp' => CarbonImmutable::parse('2026-11-25 21:00:00', 'UTC')]);

    director_page('games')
        ->set('from', '2026-11-25')->set('to', '2026-11-25')
        ->assertSee('AAA222')->assertSee('BBB333')->assertDontSee('CCC444')
        ->set('to', 'not-a-date')->assertSee('CCC444')
        ->call('clearFilters')->assertSet('from', '')->assertSee('AAA222');

    expect(director_page('dashboard')->instance()->today()['games'])->toBe(1);
});

test('the attendant filter narrows games and shifts', function () {
    game($this->team, $this->jane, ['code' => 'JJJ222']);
    game($this->team, $this->omar, ['code' => 'MMM333']);
    Shift::factory()->create(['institution_id' => $this->team->id, 'attendant_id' => $this->jane->id, 'total_cash' => 111]);
    Shift::factory()->create(['institution_id' => $this->team->id, 'attendant_id' => $this->omar->id, 'total_cash' => 222]);

    director_page('games')->set('attendant', (string) $this->jane->id)->assertSee('JJJ222')->assertDontSee('MMM333');

    $shifts = director_page('shifts')->set('attendant', (string) $this->omar->id)->instance()->shifts();
    expect($shifts->pluck('total_cash')->all())->toBe([222]);
});

test('pagination keeps its page, and changing a filter goes back to the first', function () {
    GameRecord::factory()->count(30)->create(['institution_id' => $this->team->id, 'attendant_id' => $this->jane->id]);

    director_page('games')
        ->call('gotoPage', 2)->assertSet('paginators.page', 2)
        ->set('attendant', (string) $this->jane->id)->assertSet('paginators.page', 1);
});

test('rows uploaded by someone else say so', function () {
    game($this->team, $this->jane);

    director_page('games')->assertDontSee('Uploaded by');

    game($this->team, $this->jane, ['uploaded_by' => $this->omar->id]);
    Shift::factory()->create(['institution_id' => $this->team->id, 'attendant_id' => $this->omar->id, 'uploaded_by' => $this->jane->id]);

    director_page('games')->assertSee('Uploaded by Omar Attendant');
    director_page('shifts')->assertSee('Uploaded by Jane Attendant');
});
