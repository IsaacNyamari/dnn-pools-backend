<x-layouts::app :title="__('Your account')">
    <div class="flex max-w-xl flex-col gap-4">
        <flux:heading size="xl">{{ __('Hello, :name', ['name' => auth()->user()->name]) }}</flux:heading>

        @if (auth()->user()->activeInstitutionMemberships()->exists())
            <flux:text>{{ __('Your account is for the mobile app: sign in there to start games. There is no web dashboard for attendants.') }}</flux:text>
        @else
            <flux:text>{{ __('Your account has no active institution. Ask your director or the platform administrator for access.') }}</flux:text>
        @endif

        <flux:text>{{ __('You can manage your password and two-factor authentication in settings.') }}</flux:text>

        <div>
            <flux:button :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:button>
        </div>
    </div>
</x-layouts::app>
