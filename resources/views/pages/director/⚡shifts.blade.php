<?php

use App\Livewire\Concerns\FiltersByPeriod;
use App\Livewire\Concerns\InteractsWithInstitution;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Shifts')] class extends Component {
    use FiltersByPeriod, InteractsWithInstitution;

    #[Computed]
    public function shifts()
    {
        return $this->applyFilters($this->institution->shifts()->getQuery(), 'start_time')
            ->with(['attendant:id,name', 'uploader:id,name'])
            ->latest('start_time')
            ->latest('id')
            ->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Shifts') }}</flux:heading>
        <flux:subheading>{{ $this->institution->name }}</flux:subheading>
    </div>

    <x-period-filters :people="$this->people" />

    <div class="overflow-x-auto">
        <flux:table :paginate="$this->shifts">
            <flux:table.columns>
                <flux:table.column>{{ __('Started') }}</flux:table.column>
                <flux:table.column>{{ __('Ended') }}</flux:table.column>
                <flux:table.column>{{ __('Attendant') }}</flux:table.column>
                <flux:table.column>{{ __('Location') }}</flux:table.column>
                <flux:table.column>{{ __('Cash') }}</flux:table.column>
                <flux:table.column>{{ __('M-Pesa') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->shifts as $shift)
                    <flux:table.row :key="'shift-'.$shift->id">
                        <flux:table.cell class="whitespace-nowrap">{{ \App\Support\Reporting::dateTime($shift->start_time) }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ \App\Support\Reporting::dateTime($shift->end_time) }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $shift->attendant->name }}
                            @if ($shift->uploaded_by !== $shift->attendant_id)
                                <div>
                                    <flux:badge size="sm" color="amber" data-test="uploaded-by">{{ __('Uploaded by :name', ['name' => $shift->uploader->name]) }}</flux:badge>
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $shift->location_id }}</flux:table.cell>
                        <flux:table.cell>{{ $shift->total_cash }}</flux:table.cell>
                        <flux:table.cell>{{ $shift->total_mpesa }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if ($this->shifts->isEmpty())
            <flux:text class="py-6 text-center">{{ __('No shifts found.') }}</flux:text>
        @endif
    </div>
</div>
