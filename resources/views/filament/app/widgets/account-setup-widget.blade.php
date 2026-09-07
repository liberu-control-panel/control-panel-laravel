<x-filament-widgets::widget :hidden="! $needsSetup">
    @if ($needsSetup)
        <div class="flex flex-col gap-4 rounded-2xl border border-primary-200 bg-primary-50 p-5 dark:border-primary-900 dark:bg-primary-950/30 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-semibold text-primary-700 dark:text-primary-300">Complete your workspace setup</p>
                <p class="mt-1 text-sm text-primary-900/80 dark:text-primary-100/80">Save your team profile, review optional credential storage, and see the next steps for launching a website.</p>
            </div>
            <a href="{{ $this->setupUrl() }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">Open setup guide</a>
        </div>
    @endif
</x-filament-widgets::widget>
