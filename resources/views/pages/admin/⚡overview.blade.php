<?php

use App\Enums\GameStatus;
use App\Enums\TeamRole;
use App\Livewire\Concerns\AuthorizesSystemAdmin;
use App\Models\GameRecord;
use App\Support\InstitutionSummaries;
use App\Support\Reporting;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Platform overview')] class extends Component {
    use AuthorizesSystemAdmin;

    #[Computed]
    public function institutions()
    {
        return InstitutionSummaries::all();
    }

    #[Computed]
    public function directors(): int
    {
        return InstitutionSummaries::activePeople(TeamRole::directorValues());
    }

    #[Computed]
    public function attendants(): int
    {
        return InstitutionSummaries::activePeople([TeamRole::Member->value]);
    }

    #[Computed]
    public function today(): array
    {
        $today = Reporting::today();

        return Reporting::totals(Reporting::between(GameRecord::query(), 'local_timestamp', $today, $today));
    }

    #[Computed]
    public function allTime(): array
    {
        return Reporting::totals(GameRecord::query());
    }

    #[Computed]
    public function syncDelay(): array
    {
        return Reporting::syncDelay(GameRecord::query());
    }

    #[Computed]
    public function lastUpload(): ?CarbonImmutable
    {
        $last = GameRecord::query()->toBase()->max('created_at');

        return $last ? CarbonImmutable::parse($last, 'UTC') : null;
    }

    /**
     * The latest uploads, whenever the games were played.
     */
    #[Computed]
    public function recentActivity()
    {
        return GameRecord::query()
            ->with(['institution', 'attendant:id,name', 'uploader:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->limit(15)
            ->get();
    }

    #[Computed]
    public function recentProblems()
    {
        return GameRecord::query()
            ->whereIn('status', [GameStatus::Failed, GameStatus::Expired])
            ->with(['institution', 'attendant:id,name', 'uploader:id,name'])
            ->latest('local_timestamp')
            ->latest('id')
            ->limit(10)
            ->get();
    }
}; ?>

<div class="flex flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Platform overview') }}</flux:heading>
        <flux:subheading>{{ __('Every institution. Times are in :zone.', ['zone' => config('pool.timezone')]) }}</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat :label="__('Active institutions')" :value="$this->institutions->reject(fn ($row) => $row['team']->trashed())->count()"
            :hint="__(':count suspended', ['count' => $this->institutions->filter(fn ($row) => $row['team']->trashed())->count()])" />
        <x-stat :label="__('Directors')" :value="$this->directors" />
        <x-stat :label="__('Attendants')" :value="$this->attendants" />
        <x-stat :label="__('Last upload')" :value="\App\Support\Reporting::dateTime($this->lastUpload)" />

        <x-stat :label="__('Games today')" :value="$this->today['games']"
            :hint="__('Cash :cash · M-Pesa :mpesa', ['cash' => $this->today['cash'], 'mpesa' => $this->today['mpesa']])" />
        <x-stat :label="__('Games all time')" :value="$this->allTime['games']"
            :hint="__('Cash :cash · M-Pesa :mpesa', ['cash' => $this->allTime['cash'], 'mpesa' => $this->allTime['mpesa']])" />
        <x-stat :label="__('Failed / expired today')" :value="$this->today['failed'].' / '.$this->today['expired']"
            :hint="__('All time :failed / :expired', ['failed' => $this->allTime['failed'], 'expired' => $this->allTime['expired']])" />
        <x-stat :label="__('Typical sync delay (7 days)')" :value="\App\Support\Reporting::duration($this->syncDelay['median'])"
            :hint="__('95% within :p95 · worst :max · :count uploads', ['p95' => \App\Support\Reporting::duration($this->syncDelay['p95']), 'max' => \App\Support\Reporting::duration($this->syncDelay['max']), 'count' => $this->syncDelay['count']])" />
    </div>

    <div class="overflow-x-auto">
        <flux:heading size="lg" class="mb-2">{{ __('Institutions') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Institution') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Directors') }}</flux:table.column>
                <flux:table.column>{{ __('Attendants') }}</flux:table.column>
                <flux:table.column>{{ __('Games today') }}</flux:table.column>
                <flux:table.column>{{ __('Games all time') }}</flux:table.column>
                <flux:table.column>{{ __('Last upload') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->institutions as $row)
                    <flux:table.row :key="'institution-'.$row['team']->id">
                        <flux:table.cell>
                            <flux:link :href="route('admin.institutions.show', $row['team']->id)" wire:navigate>{{ $row['team']->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$row['team']->trashed() ? 'red' : 'green'">{{ $row['team']->trashed() ? __('Suspended') : __('Active') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $row['directors'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['attendants'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['today'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['games'] }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ \App\Support\Reporting::dateTime($row['last_upload']) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        @if ($this->institutions->isEmpty())
            <flux:text class="py-6 text-center">{{ __('No institutions yet.') }}</flux:text>
        @endif
    </div>

    <div>
        <flux:heading size="lg" class="mb-2">{{ __('Recent activity') }}</flux:heading>
        <x-games-table :records="$this->recentActivity" show-institution :empty="__('No uploads yet.')" />
    </div>

    <div>
        <flux:heading size="lg" class="mb-2">{{ __('Recent failed and expired games') }}</flux:heading>
        <x-games-table :records="$this->recentProblems" show-institution :empty="__('None.')" />
    </div>
</div>
