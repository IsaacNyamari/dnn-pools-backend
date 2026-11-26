<?php

use App\Livewire\Concerns\FiltersByPeriod;
use App\Livewire\Concerns\InteractsWithInstitution;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Games')] class extends Component {
    use FiltersByPeriod, InteractsWithInstitution;

    #[Computed]
    public function records()
    {
        return $this->applyFilters($this->institution->gameRecords()->getQuery(), 'local_timestamp')
            ->with(['attendant:id,name', 'uploader:id,name'])
            ->latest('local_timestamp')
            ->latest('id')
            ->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Games') }}</flux:heading>
        <flux:subheading>{{ $this->institution->name }}</flux:subheading>
    </div>

    <x-period-filters :people="$this->people" />

    <x-games-table :records="$this->records" :paginate="$this->records" />
</div>
