<x-filament-panels::page>
    <x-filament::section heading="Workspace setup" icon="heroicon-o-sparkles">
        <x-slot name="description">Save your team profile, review optional credentials, and plan your first website. Only the team owner can change these settings.</x-slot>
        <p>{{ count($completedSteps) }} of 3 setup steps complete</p>
        <nav aria-label="Setup progress">
            @foreach ([1 => 'Workspace profile', 2 => 'Optional credentials', 3 => 'Review and next steps'] as $number => $label)
                <x-filament::button wire:key="setup-step-{{ $number }}" type="button" wire:click="goToStep({{ $number }})"
                    :color="$step === $number ? 'primary' : 'gray'"
                    :disabled="$number > 1 && count(array_diff(range(1, $number - 1), $completedSteps)) > 0"
                    :aria-current="$step === $number ? 'step' : null">
                    {{ $number }}. {{ $label }}{{ in_array($number, $completedSteps, true) ? ' — complete' : '' }}
                </x-filament::button>
            @endforeach
        </nav>
    </x-filament::section>

    @if ($errors->any())
        <x-filament::section heading="Please check your setup" icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <ul role="alert">
                @foreach ($errors->all() as $error)
                    <li wire:key="setup-error-{{ $loop->index }}">{{ $error }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($step === 1)
        <form wire:submit="saveProfile">
            <x-filament::section heading="Tell us about your team">
                <x-filament::fieldset label="Team name" required>
                    <x-filament::input.wrapper :valid="! $errors->has('teamName')">
                        <x-filament::input id="setup-team-name" aria-label="Team name" wire:model="teamName" required maxlength="255" />
                    </x-filament::input.wrapper>
                </x-filament::fieldset>
                <x-filament::fieldset label="Workspace timezone" required>
                    <x-filament::input.wrapper :valid="! $errors->has('timezone')">
                        <x-filament::input.select aria-label="Workspace timezone" wire:model="timezone" required>
                            @foreach ($this->timezoneOptions() as $value => $label)
                                <option wire:key="timezone-{{ $value }}" value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </x-filament::fieldset>
                <x-slot name="footer">
                    <x-filament::button type="submit" wire:loading.attr="disabled">Save and continue</x-filament::button>
                </x-slot>
            </x-filament::section>
        </form>
    @elseif ($step === 2)
        <x-filament::section heading="Understand connection readiness" icon="heroicon-o-information-circle">
            <p>Team credentials below are encrypted storage only. Saving them does not enable platform sign-in, connect an account, activate billing, or provision hosting. Those connections require a working integration adapter.</p>
            <p>You do not need OAuth client secrets or a Stripe key to complete workspace setup. Leave unused providers blank. To connect your own GitHub or Google account, use the connected-accounts section of your profile.</p>
            @if (\Illuminate\Support\Facades\Route::has('profile.show'))
                <x-filament::button tag="a" :href="route('profile.show')" color="gray">Manage connected accounts</x-filament::button>
            @endif
        </x-filament::section>
        <form wire:submit="saveIntegrations">
            <x-filament::section heading="Optional team credential storage">
                <x-slot name="description">Provide a complete OAuth ID/secret pair. Blank fields preserve stored values. Changing a client ID requires its new secret. Saved secrets are never loaded back into this form.</x-slot>
                @foreach ([
                    'GitHub OAuth' => ['githubClientId' => ['Client ID', 'text', 255], 'githubClientSecret' => ['Client secret', 'password', 1000]],
                    'Google OAuth' => ['googleClientId' => ['Client ID', 'text', 255], 'googleClientSecret' => ['Client secret', 'password', 1000]],
                    'Stripe API' => ['stripeSecret' => ['Secret key', 'password', 1000]],
                ] as $provider => $fields)
                    <x-filament::fieldset wire:key="provider-{{ $provider }}" :label="$provider">
                        <x-filament::badge color="gray">{{ ($this->integrationStatus[$provider] ?? false) ? 'Stored — not verified or connected' : 'Not stored — optional' }}</x-filament::badge>
                        @foreach ($fields as $field => [$label, $type, $maxLength])
                            <div wire:key="credential-{{ $field }}">
                                <label for="{{ $field }}">{{ $label }}</label>
                                <x-filament::input.wrapper :valid="! $errors->has($field)">
                                    <x-filament::input :id="$field" wire:model="{{ $field }}" :type="$type" :maxlength="$maxLength" autocomplete="off" />
                                </x-filament::input.wrapper>
                            </div>
                        @endforeach
                    </x-filament::fieldset>
                @endforeach
                <x-slot name="footer">
                    <x-filament::button type="button" wire:click="goToStep(1)" color="gray">Back</x-filament::button>
                    <x-filament::button type="submit" wire:loading.attr="disabled">Save optional credentials and continue</x-filament::button>
                </x-slot>
            </x-filament::section>
        </form>
    @else
        <x-filament::section heading="Review your workspace" icon="heroicon-o-clipboard-document-check">
            <p>Workspace: {{ $teamName }} · Timezone: {{ $timezone }}</p>
            <p>Your profile is saved and optional credentials have been reviewed. This completes account setup, not infrastructure provisioning.</p>
            @foreach ($this->integrationStatus as $provider => $stored)
                <p wire:key="review-{{ $provider }}">{{ $provider }}: {{ $stored ? 'Stored — connection not verified' : 'Skipped — no credentials stored' }}</p>
            @endforeach
            <x-slot name="footer">
                <x-filament::button type="button" wire:click="goToStep(2)" color="gray">Review credentials</x-filament::button>
                <x-filament::button type="button" wire:click="finish" wire:loading.attr="disabled">Finish account setup</x-filament::button>
            </x-slot>
        </x-filament::section>
        <x-filament::section heading="Before launching your first website">
            <ol>
                <li>Ask your hosting administrator to connect a server or cluster and assign your hosting account and quota.</li>
                <li>Add your domain and configure its runtime and document root.</li>
                <li>Point DNS at the hosting service, deploy your site, and issue its HTTPS certificate.</li>
                <li>Configure a backup destination and schedule, then verify a test restore.</li>
            </ol>
            <p>Use narrowly scoped personal API tokens only when automation needs them.</p>
            <x-filament::button tag="a" :href="$this->securityUrl()" color="gray">Manage API access</x-filament::button>
            @if (\App\Filament\App\Pages\Websites::canAccess())
                <x-filament::button tag="a" :href="\App\Filament\App\Pages\Websites::getUrl(panel: 'app')" color="gray">Review websites</x-filament::button>
            @endif
        </x-filament::section>
    @endif
    <p wire:loading role="status">Updating setup…</p>
</x-filament-panels::page>
