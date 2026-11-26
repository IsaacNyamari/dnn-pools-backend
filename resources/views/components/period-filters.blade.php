@props(['people' => null])

<div class="flex flex-wrap items-end gap-3">
    <flux:input wire:model.live="from" type="date" :label="__('From')" class="max-w-44" />
    <flux:input wire:model.live="to" type="date" :label="__('To')" class="max-w-44" />

    @if ($people)
        <flux:select wire:model.live="attendant" :label="__('Attendant')" class="max-w-56">
            <flux:select.option value="">{{ __('Everyone') }}</flux:select.option>
            @foreach ($people as $id => $name)
                <flux:select.option :value="$id">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>
    @endif

    <flux:button variant="ghost" wire:click="clearFilters">{{ __('Clear') }}</flux:button>
</div>
