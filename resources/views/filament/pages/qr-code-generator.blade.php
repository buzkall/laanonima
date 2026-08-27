<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-2">
        <div>
            {{ $this->form }}
        </div>

        <div class="flex items-center justify-center rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            @if ($svg = $this->getPreviewSvg())
                {{-- Safe: the SVG is built server-side from our own brand asset. The
                     URL only ever enters the QR matrix, never this markup. Do not
                     repoint this at anything a user can upload. --}}
                <div class="w-full max-w-sm [&>svg]:h-auto [&>svg]:w-full">
                    {!! $svg !!}
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('qr.placeholders.preview') }}
                </p>
            @endif
        </div>
    </div>

    <x-filament::section collapsible collapsed :heading="__('qr.sections.printing_tips.heading')">
        <ul class="list-disc space-y-1 pl-5 text-sm">
            <li>{{ __('qr.sections.printing_tips.items.thermal') }}</li>
            <li>{{ __('qr.sections.printing_tips.items.vector') }}</li>
            <li>{{ __('qr.sections.printing_tips.items.test') }}</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
