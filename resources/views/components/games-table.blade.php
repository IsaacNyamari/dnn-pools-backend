@props(['records', 'showInstitution' => false, 'paginate' => null, 'empty' => __('No games found.')])

<div {{ $attributes->class('overflow-x-auto') }}>
    <flux:table :paginate="$paginate">
        <flux:table.columns>
            <flux:table.column>{{ __('Played') }}</flux:table.column>
            @if ($showInstitution)
                <flux:table.column>{{ __('Institution') }}</flux:table.column>
            @endif
            <flux:table.column>{{ __('Attendant') }}</flux:table.column>
            <flux:table.column>{{ __('Table') }}</flux:table.column>
            <flux:table.column>{{ __('Code') }}</flux:table.column>
            <flux:table.column>{{ __('Payment') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Sync delay') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($records as $record)
                <flux:table.row :key="'game-'.$record->id">
                    <flux:table.cell class="whitespace-nowrap">{{ \App\Support\Reporting::dateTime($record->local_timestamp) }}</flux:table.cell>
                    @if ($showInstitution)
                        <flux:table.cell>{{ $record->institution->name }}</flux:table.cell>
                    @endif
                    <flux:table.cell>
                        {{ $record->attendant->name }}
                        @if ($record->uploaded_by !== $record->attendant_id)
                            <div>
                                <flux:badge size="sm" color="amber" data-test="uploaded-by">{{ __('Uploaded by :name', ['name' => $record->uploader->name]) }}</flux:badge>
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $record->table_id }} <span class="text-zinc-400">{{ $record->location_id }}</span></flux:table.cell>
                    <flux:table.cell class="font-mono">{{ $record->code }}</flux:table.cell>
                    <flux:table.cell>{{ $record->payment_method ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$record->status->color()">{{ $record->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ \App\Support\Reporting::duration(\App\Support\Reporting::delaySeconds($record)) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    @if (count($records) === 0)
        <flux:text class="py-6 text-center">{{ $empty }}</flux:text>
    @endif
</div>
