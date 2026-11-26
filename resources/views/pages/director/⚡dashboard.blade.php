<?php

use App\Livewire\Concerns\FiltersByPeriod;
use App\Livewire\Concerns\InteractsWithInstitution;
use App\Support\Reporting;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    use FiltersByPeriod, InteractsWithInstitution;

    #[Computed]
    public function today(): array
    {
        $today = Reporting::today();

        return Reporting::totals(Reporting::between($this->institution->gameRecords()->getQuery(), 'local_timestamp', $today, $today));
    }

    #[Computed]
    public function periodTotals(): array
    {
        return Reporting::totals($this->applyFilters($this->institution->gameRecords()->getQuery(), 'local_timestamp'));
    }

    /**
     * Totals per attendant for the filtered period, including their closed shifts.
     */
    #[Computed]
    public function perAttendant()
    {
        $games = Reporting::selectTotals($this->applyFilters($this->institution->gameRecords()->getQuery(), 'local_timestamp'))
            ->addSelect('attendant_id')
            ->groupBy('attendant_id')
            ->toBase()
            ->get()
            ->keyBy('attendant_id');

        $shifts = $this->applyFilters($this->institution->shifts()->getQuery(), 'start_time')
            ->selectRaw('attendant_id, count(*) as shifts, sum(total_cash) as shift_cash, sum(total_mpesa) as shift_mpesa')
            ->groupBy('attendant_id')
            ->toBase()
            ->get()
            ->keyBy('attendant_id');

        return $games->keys()->merge($shifts->keys())->unique()
            ->map(fn ($id) => [
                'id' => $id,
                'name' => $this->people[$id] ?? __('Unknown'),
                'games' => (int) ($games[$id]->games ?? 0),
                'cash' => (int) ($games[$id]->cash ?? 0),
                'mpesa' => (int) ($games[$id]->mpesa ?? 0),
                'failed' => (int) ($games[$id]->failed ?? 0) + (int) ($games[$id]->expired ?? 0),
                'shifts' => (int) ($shifts[$id]->shifts ?? 0),
                'shift_cash' => (int) ($shifts[$id]->shift_cash ?? 0),
                'shift_mpesa' => (int) ($shifts[$id]->shift_mpesa ?? 0),
            ])
            ->sortByDesc('games')
            ->values();
    }

    #[Computed]
    public function perTable()
    {
        return Reporting::selectTotals($this->applyFilters($this->institution->gameRecords()->getQuery(), 'local_timestamp'))
            ->addSelect('location_id', 'table_id')
            ->groupBy('location_id', 'table_id')
            ->orderBy('location_id')
            ->orderBy('table_id')
            ->toBase()
            ->get();
    }
}; ?>

<div class="flex flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ $this->institution->name }}</flux:heading>
        <flux:subheading>{{ __('Games and takings') }}</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="today-stats">
        <x-stat :label="__('Games today')" :value="$this->today['games']" />
        <x-stat :label="__('Cash today')" :value="$this->today['cash']" />
        <x-stat :label="__('M-Pesa today')" :value="$this->today['mpesa']" />
        <x-stat :label="__('Failed or expired today')" :value="$this->today['failed'] + $this->today['expired']" />
    </div>

    <flux:separator />

    <x-period-filters :people="$this->people" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat :label="__('Games in period')" :value="$this->periodTotals['games']" :hint="$from === '' && $to === '' ? __('All time') : null" />
        <x-stat :label="__('Cash')" :value="$this->periodTotals['cash']" />
        <x-stat :label="__('M-Pesa')" :value="$this->periodTotals['mpesa']" />
        <x-stat :label="__('Failed or expired')" :value="$this->periodTotals['failed'] + $this->periodTotals['expired']" :hint="__(':count unconfirmed', ['count' => $this->periodTotals['unconfirmed']])" />
    </div>

    <div class="overflow-x-auto">
        <flux:heading size="lg" class="mb-2">{{ __('Totals per attendant') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Attendant') }}</flux:table.column>
                <flux:table.column>{{ __('Games') }}</flux:table.column>
                <flux:table.column>{{ __('Cash') }}</flux:table.column>
                <flux:table.column>{{ __('M-Pesa') }}</flux:table.column>
                <flux:table.column>{{ __('Failed or expired') }}</flux:table.column>
                <flux:table.column>{{ __('Shifts') }}</flux:table.column>
                <flux:table.column>{{ __('Shift cash / M-Pesa') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->perAttendant as $row)
                    <flux:table.row :key="'attendant-'.$row['id']">
                        <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['games'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['cash'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['mpesa'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['failed'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['shifts'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['shift_cash'] }} / {{ $row['shift_mpesa'] }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        @if ($this->perAttendant->isEmpty())
            <flux:text class="py-6 text-center">{{ __('Nothing in this period.') }}</flux:text>
        @endif
    </div>

    <div class="overflow-x-auto">
        <flux:heading size="lg" class="mb-2">{{ __('Totals per table') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Table') }}</flux:table.column>
                <flux:table.column>{{ __('Location') }}</flux:table.column>
                <flux:table.column>{{ __('Games') }}</flux:table.column>
                <flux:table.column>{{ __('Cash') }}</flux:table.column>
                <flux:table.column>{{ __('M-Pesa') }}</flux:table.column>
                <flux:table.column>{{ __('Failed or expired') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->perTable as $row)
                    <flux:table.row :key="'table-'.$row->location_id.'-'.$row->table_id">
                        <flux:table.cell>{{ $row->table_id }}</flux:table.cell>
                        <flux:table.cell>{{ $row->location_id }}</flux:table.cell>
                        <flux:table.cell>{{ $row->games }}</flux:table.cell>
                        <flux:table.cell>{{ $row->cash }}</flux:table.cell>
                        <flux:table.cell>{{ $row->mpesa }}</flux:table.cell>
                        <flux:table.cell>{{ $row->failed + $row->expired }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        @if ($this->perTable->isEmpty())
            <flux:text class="py-6 text-center">{{ __('Nothing in this period.') }}</flux:text>
        @endif
    </div>
</div>
