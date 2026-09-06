<x-filament-panels::page>
    <div class="mx-auto w-full max-w-5xl space-y-8">
        <header class="rounded-2xl bg-gradient-to-br from-primary-700 to-primary-500 p-6 text-white shadow-sm md:p-8">
            <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-primary-100">Getting started</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">Let’s get your account ready</h1>
                    <p class="mt-3 text-sm leading-6 text-primary-50">Complete the essentials once, connect the services your project needs, then invite your team.</p>
                </div>
                <div class="rounded-xl bg-white/15 px-4 py-3 text-sm backdrop-blur"><span class="font-semibold">{{ count($completedSteps) }} of 3</span> steps complete</div>
            </div>
        </header>

        <nav aria-label="Setup progress" class="grid gap-3 md:grid-cols-3">
            @foreach ([1 => ['Workspace profile', 'Name and timezone', 'heroicon-o-building-office-2'], 2 => ['Integrations', 'OAuth and API keys', 'heroicon-o-key'], 3 => ['Ready to go', 'Review and finish', 'heroicon-o-check-circle']] as $number => [$label, $description, $icon])
                <button type="button" wire:click="goToStep({{ $number }})" @disabled($number > 1 && ! in_array($number - 1, $completedSteps, true)) class="group rounded-2xl border p-4 text-left transition hover:-translate-y-0.5 hover:border-primary-500 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50 {{ $step === $number ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500 dark:bg-primary-950/30' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900' }}">
                    <span class="flex items-center gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl {{ $step === $number ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-500 dark:bg-gray-800' }}"><x-filament::icon :icon="$icon" class="size-5" /></span><span><span class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Step {{ $number }}</span><span class="mt-1 block font-semibold text-gray-950 dark:text-white">{{ $label }}</span></span></span>
                    <span class="mt-3 block text-sm text-gray-600 dark:text-gray-400">{{ $description }}</span>
                    @if (in_array($number, $completedSteps, true))<span class="mt-3 block text-xs font-semibold text-success-600 dark:text-success-400">Complete</span>@endif
                </button>
            @endforeach
        </nav>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900 md:p-8">
            @if ($step === 1)
                <form wire:submit="saveProfile" class="space-y-7">
                    <div><h2 class="text-xl font-semibold text-gray-950 dark:text-white">Tell us about your team</h2><p class="mt-2 text-sm text-gray-600 dark:text-gray-400">This information personalises the workspace for everyone on your team.</p></div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-800 dark:text-gray-200">Team name<input wire:model="teamName" required maxlength="255" class="mt-2 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 dark:border-gray-600 dark:bg-gray-800"></label>
                        <label class="block text-sm font-medium text-gray-800 dark:text-gray-200">Workspace timezone<select wire:model="timezone" required class="mt-2 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 dark:border-gray-600 dark:bg-gray-800">@foreach ($this->timezoneOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    </div>
                    @error('teamName')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                    @error('timezone')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                    <button type="submit" class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500" wire:loading.attr="disabled">Save and continue <span aria-hidden="true">→</span></button>
                </form>
            @elseif ($step === 2)
                <form wire:submit="saveIntegrations" class="space-y-7">
                    <div><h2 class="text-xl font-semibold text-gray-950 dark:text-white">Connect the services your project needs</h2><p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Configure OAuth providers for social sign-in and API credentials for billing or automation. Secrets are encrypted before storage and never shown again.</p></div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"><div class="mb-4 flex items-center justify-between gap-3"><span class="font-semibold">GitHub OAuth</span><span class="text-xs {{ ($this->integrationStatus()['GitHub OAuth'] ?? false) ? 'text-success-600' : 'text-gray-500' }}">{{ ($this->integrationStatus()['GitHub OAuth'] ?? false) ? 'Configured' : 'Optional' }}</span></div><div class="space-y-3"><input wire:model="githubClientId" placeholder="Client ID" aria-label="GitHub OAuth client ID" maxlength="255" class="block w-full rounded-xl border-gray-300 px-3 py-2.5 dark:border-gray-600 dark:bg-gray-800"><input wire:model="githubClientSecret" type="password" placeholder="Client secret" aria-label="GitHub OAuth client secret" maxlength="1000" autocomplete="new-password" class="block w-full rounded-xl border-gray-300 px-3 py-2.5 dark:border-gray-600 dark:bg-gray-800"></div></div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"><div class="mb-4 flex items-center justify-between gap-3"><span class="font-semibold">Google OAuth</span><span class="text-xs {{ ($this->integrationStatus()['Google OAuth'] ?? false) ? 'text-success-600' : 'text-gray-500' }}">{{ ($this->integrationStatus()['Google OAuth'] ?? false) ? 'Configured' : 'Optional' }}</span></div><div class="space-y-3"><input wire:model="googleClientId" placeholder="Client ID" aria-label="Google OAuth client ID" maxlength="255" class="block w-full rounded-xl border-gray-300 px-3 py-2.5 dark:border-gray-600 dark:bg-gray-800"><input wire:model="googleClientSecret" type="password" placeholder="Client secret" aria-label="Google OAuth client secret" maxlength="1000" autocomplete="new-password" class="block w-full rounded-xl border-gray-300 px-3 py-2.5 dark:border-gray-600 dark:bg-gray-800"></div></div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700 md:col-span-2"><div class="mb-4 flex items-center justify-between gap-3"><span class="font-semibold">Stripe API</span><span class="text-xs {{ ($this->integrationStatus()['Stripe API'] ?? false) ? 'text-success-600' : 'text-gray-500' }}">{{ ($this->integrationStatus()['Stripe API'] ?? false) ? 'Configured' : 'Optional' }}</span></div><input wire:model="stripeSecret" type="password" placeholder="Secret key (sk_live_… or sk_test_…)" aria-label="Stripe secret key" maxlength="1000" autocomplete="new-password" class="block w-full rounded-xl border-gray-300 px-3 py-2.5 dark:border-gray-600 dark:bg-gray-800"></div>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-800/70 dark:text-gray-300"><span class="font-semibold text-gray-900 dark:text-white">No credentials yet?</span> Leave fields blank to skip them. You can update integrations later, and create personal API tokens from <a href="{{ $this->securityUrl() }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Account security</a>.</div>
                    <div class="flex gap-3"><button type="button" wire:click="goToStep(1)" class="rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-semibold dark:border-gray-600">Back</button><button type="submit" class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500" wire:loading.attr="disabled">Save and continue <span aria-hidden="true">→</span></button></div>
                </form>
            @else
                <div class="space-y-7"><div><h2 class="text-xl font-semibold text-gray-950 dark:text-white">Your workspace is ready</h2><p class="mt-2 text-sm text-gray-600 dark:text-gray-400">The essentials for <strong>{{ $teamName }}</strong> are saved. Use this quick checklist before you start managing infrastructure.</p></div><div class="grid gap-3 sm:grid-cols-3"><div class="rounded-xl bg-success-50 p-4 text-sm text-success-800 dark:bg-success-950/30 dark:text-success-200"><x-filament::icon icon="heroicon-o-check-circle" class="mb-2 size-5" /><p class="font-semibold">Workspace profile</p><p class="mt-1 text-xs">Name and timezone saved.</p></div><div class="rounded-xl bg-success-50 p-4 text-sm text-success-800 dark:bg-success-950/30 dark:text-success-200"><x-filament::icon icon="heroicon-o-check-circle" class="mb-2 size-5" /><p class="font-semibold">Integrations</p><p class="mt-1 text-xs">Credentials saved securely.</p></div><div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-700 dark:bg-gray-800/70 dark:text-gray-300"><x-filament::icon icon="heroicon-o-key" class="mb-2 size-5" /><p class="font-semibold">API access</p><p class="mt-1 text-xs">Create a token when automation is ready.</p></div></div><div class="flex flex-wrap gap-3"><button type="button" wire:click="goToStep(2)" class="rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-semibold dark:border-gray-600">Review integrations</button><a href="{{ $this->securityUrl() }}" class="rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-semibold dark:border-gray-600">Manage API access</a><button type="button" wire:click="finish" class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500" wire:loading.attr="disabled">Go to dashboard <span aria-hidden="true">→</span></button></div></div>
            @endif
            <p wire:loading role="status" class="mt-5 text-sm text-gray-500">Saving your setup…</p>
        </section>
    </div>
</x-filament-panels::page>
