@props(['label', 'value', 'hint' => null])

<flux:card {{ $attributes->class('!p-4') }}>
    <flux:text>{{ $label }}</flux:text>
    <flux:heading size="xl" class="mt-1">{{ $value }}</flux:heading>
    @if ($hint)
        <flux:text size="sm" class="mt-1">{{ $hint }}</flux:text>
    @endif
</flux:card>
