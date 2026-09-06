<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Settings\TeamSetupDefinition;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Liberu\Foundation\Organizations\Models\Team;
use Liberu\Foundation\Organizations\Services\CurrentTeamResolver;
use Liberu\Foundation\SessionsDevicesFilament\Pages\AccountSecurity;
use Liberu\Foundation\Settings\Services\ScopedSettings;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

final class AccountSetup extends Page
{
    protected string $view = 'filament.app.pages.account-setup';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Getting started';

    protected static ?string $navigationLabel = 'Setup guide';

    protected static ?int $navigationSort = 1;

    #[Locked]
    public int $step = 1;

    #[Locked]
    public string $teamId = '';

    #[Locked]
    public int $revision = 0;

    public string $teamName = '';

    public string $timezone = 'UTC';

    public string $githubClientId = '';

    public string $githubClientSecret = '';

    public string $googleClientId = '';

    public string $googleClientSecret = '';

    public string $stripeSecret = '';

    /** @var list<int> */
    #[Locked]
    public array $completedSteps = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $team = $user === null ? null : app(CurrentTeamResolver::class)->resolve($user, $user->current_team_id);

        return $team !== null && (string) $team->getAttribute('user_id') === (string) auth()->id();
    }

    public function mount(CurrentTeamResolver $resolver, ScopedSettings $settings): void
    {
        $team = $this->currentTeam($resolver);
        $this->teamId = (string) $team->getKey();
        $stored = $settings->resolve('team.setup', ['team' => $team->getKey()], [
            'completed_steps' => [],
            'team_name' => (string) $team->getAttribute('name'),
            'timezone' => 'UTC',
            'integrations' => [],
        ]);

        $this->teamName = (string) ($stored['team_name'] ?? $team->getAttribute('name'));
        $this->timezone = (string) ($stored['timezone'] ?? 'UTC');
        $integrations = (array) ($stored['integrations'] ?? []);
        $this->githubClientId = (string) ($integrations['github_client_id'] ?? '');
        $this->googleClientId = (string) ($integrations['google_client_id'] ?? '');
        $this->completedSteps = array_values(array_map('intval', $stored['completed_steps'] ?? []));
        $this->revision = (int) ($stored['revision'] ?? 0);
        $nextStep = ! in_array(1, $this->completedSteps, true) ? 1 : (! in_array(2, $this->completedSteps, true) ? 2 : 3);
        $this->step = min(max(request()->integer('step', $nextStep), 1), $nextStep);
    }

    public function hydrate(CurrentTeamResolver $resolver): void
    {
        $this->currentTeam($resolver);
    }

    public function saveProfile(ScopedSettings $settings, CurrentTeamResolver $resolver): void
    {
        $team = $this->currentTeam($resolver);
        $this->teamName = trim($this->teamName);
        $this->validate([
            'teamName' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
        ]);

        $this->persist($settings, $team, 1, ['team_name' => $this->teamName, 'timezone' => $this->timezone]);
        $this->step = 2;
    }

    public function saveIntegrations(ScopedSettings $settings, CurrentTeamResolver $resolver): void
    {
        $team = $this->currentTeam($resolver);
        $this->requireSteps($settings, $team, [1]);
        $this->validate([
            'githubClientId' => ['nullable', 'string', 'max:255'],
            'githubClientSecret' => ['nullable', 'string', 'max:1000'],
            'googleClientId' => ['nullable', 'string', 'max:255'],
            'googleClientSecret' => ['nullable', 'string', 'max:1000'],
            'stripeSecret' => ['nullable', 'string', 'max:1000'],
        ]);

        $stored = $settings->resolve('team.setup', ['team' => $team->getKey()], ['integrations' => []]);
        $integrations = (array) ($stored['integrations'] ?? []);
        $values = [
            'github_client_id' => $this->githubClientId,
            'github_client_secret' => $this->githubClientSecret,
            'google_client_id' => $this->googleClientId,
            'google_client_secret' => $this->googleClientSecret,
            'stripe_secret' => $this->stripeSecret,
        ];

        foreach ($values as $key => $value) {
            if (trim($value) !== '') {
                $integrations[$key] = trim($value);
            }
        }

        foreach (['github' => 'githubClientSecret', 'google' => 'googleClientSecret'] as $provider => $secretField) {
            $id = $integrations[$provider.'_client_id'] ?? '';
            $secret = $integrations[$provider.'_client_secret'] ?? '';
            if (filled($id) !== filled($secret)) {
                throw ValidationException::withMessages([$secretField => 'Provide both the client ID and client secret, or leave both blank.']);
            }
            if (filled($id) && $id !== ($stored['integrations'][$provider.'_client_id'] ?? '') && blank($this->{$secretField})) {
                throw ValidationException::withMessages([$secretField => 'Enter a new secret when changing the client ID.']);
            }
        }

        $this->persist($settings, $team, 2, ['integrations' => $integrations]);
        $this->githubClientSecret = '';
        $this->googleClientSecret = '';
        $this->stripeSecret = '';
        $this->step = 3;
    }

    public function finish(ScopedSettings $settings, CurrentTeamResolver $resolver): void
    {
        $team = $this->currentTeam($resolver);
        $this->requireSteps($settings, $team, [1, 2]);
        $this->persist($settings, $team, 3, []);
        $this->redirect(Dashboard::getUrl(panel: 'app'));
    }

    public function goToStep(int $step, ScopedSettings $settings, CurrentTeamResolver $resolver): void
    {
        $team = $this->currentTeam($resolver);
        if ($step < 1 || $step > 3) {
            return;
        }

        $this->requireSteps($settings, $team, $step === 1 ? [] : range(1, $step - 1));

        if ($step === 3) {
            $stored = $settings->resolve('team.setup', ['team' => $team->getKey()]);
            $this->teamName = (string) ($stored['team_name'] ?? $team->getAttribute('name'));
            $this->timezone = (string) ($stored['timezone'] ?? 'UTC');
        }

        $this->step = $step;
    }

    public function securityUrl(): string
    {
        return AccountSecurity::getUrl(panel: 'app');
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function integrationStatus(): array
    {
        $team = $this->currentTeam(app(CurrentTeamResolver::class));
        $integrations = app(ScopedSettings::class)->resolve('team.setup', ['team' => $team->getKey()], ['integrations' => []])['integrations'] ?? [];

        return [
            'GitHub OAuth' => filled($integrations['github_client_id'] ?? null) && filled($integrations['github_client_secret'] ?? null),
            'Google OAuth' => filled($integrations['google_client_id'] ?? null) && filled($integrations['google_client_secret'] ?? null),
            'Stripe API' => filled($integrations['stripe_secret'] ?? null),
        ];
    }

    /** @return array<string, string> */
    public function timezoneOptions(): array
    {
        return collect(\DateTimeZone::listIdentifiers())
            ->mapWithKeys(fn (string $timezone): array => [$timezone => str_replace('_', ' ', $timezone)])
            ->all();
    }

    private function currentTeam(CurrentTeamResolver $resolver): Team
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $team = $resolver->resolve($user, $user->current_team_id);

        abort_if($team === null, 403, 'Choose an active team before opening setup.');
        abort_unless((string) $team->getAttribute('user_id') === (string) $user->getAuthIdentifier(), 403, 'Only the team owner can manage setup.');
        abort_if($this->teamId !== '' && $this->teamId !== (string) $team->getKey(), 409, 'Your active team changed. Reload setup before saving.');

        return $team;
    }

    /** @param array<string, mixed> $changes */
    private function persist(ScopedSettings $settings, Team $team, int $step, array $changes): void
    {
        DB::transaction(function () use ($settings, $team, $step, $changes): void {
            $lockedTeam = Team::query()->whereKey($team->getKey())->lockForUpdate()->firstOrFail();
            abort_unless((string) $lockedTeam->getAttribute('user_id') === (string) auth()->id() && $lockedTeam->getAttribute('status') === 'active', 403);
            $existing = $settings->resolve('team.setup', ['team' => $team->getKey()], [
                'completed_steps' => [],
                'team_name' => (string) $team->getAttribute('name'),
                'timezone' => $this->timezone,
                'integrations' => [],
            ]);
            abort_if((int) ($existing['revision'] ?? 0) !== $this->revision, 409, 'Setup was updated in another tab. Reload before saving.');
            $completed = array_values(array_unique([...array_filter(array_map('intval', $existing['completed_steps'] ?? []), fn (int $completedStep): bool => $completedStep < $step), $step]));
            $revision = $this->revision + 1;
            $settings->put(new TeamSetupDefinition(), 'team', (string) $team->getKey(), array_replace($existing, $changes, ['completed_steps' => $completed, 'revision' => $revision]));
            if ($step === 1) {
                $lockedTeam->forceFill(['name' => $changes['team_name']])->save();
            }
            $this->completedSteps = $completed;
            $this->revision = $revision;
            unset($this->integrationStatus);
        });
    }

    /** @param list<int> $required */
    private function requireSteps(ScopedSettings $settings, Team $team, array $required): void
    {
        $stored = $settings->resolve('team.setup', ['team' => $team->getKey()], ['completed_steps' => []]);
        if (array_diff($required, array_map('intval', $stored['completed_steps'] ?? [])) !== []) {
            throw ValidationException::withMessages(['setup' => 'Complete the preceding setup steps before continuing.']);
        }
    }
}
