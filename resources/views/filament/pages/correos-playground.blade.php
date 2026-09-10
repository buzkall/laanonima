<x-filament-panels::page>
    @if ($this->isOffline())
        <x-filament::section :heading="__('correos.fake.heading')">
            <p class="text-sm">{{ __('correos.fake.body') }}</p>
        </x-filament::section>
    @elseif (! $this->hasCredentials())
        <x-filament::section :heading="__('correos.credentials.heading')">
            <p class="text-sm">{{ __('correos.credentials.body') }}</p>
        </x-filament::section>
    @endif

    <x-filament::section :heading="__('correos.environment.heading')" compact>
        <p class="text-sm">
            {{ __('correos.environment.body') }}
            <code class="text-xs">{{ $this->getEnvironmentUrl() }}</code>
        </p>
    </x-filament::section>

    {{ $this->form }}

    <x-filament::section :heading="$outputTitle ?? __('correos.output.heading')" collapsible>
        @if ($output)
            <pre class="overflow-x-auto text-xs leading-relaxed">{{ $output }}</pre>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('correos.output.empty') }}</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
