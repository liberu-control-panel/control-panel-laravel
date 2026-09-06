<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Settings\TeamSetupDefinition;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Liberu\Foundation\Organizations\Models\Team;
use Liberu\Foundation\Organizations\Services\CurrentTeamResolver;
use Liberu\Foundation\SessionsDevicesFilament\Pages\AccountSecurity;
use Liberu\Foundation\Settings\Services\ScopedSettings;

final class AccountSetup extends Page
{
    protected string $view = 'filament.app.pages.account-setup';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Getting started';

    protected static ?string $navigationLabel = 'Setup guide';

    protected static ?int $navigationSort = 1;

    public int $step = 1;

    public string $teamName = '';

    public string $timezone = 'UTC';

    public string $githubClientId = '';

    public string $githubClientSecret = '';

    public string $googleClientId = '';

    public string $googleClientSecret = '';

    public string $stripeSecret = '';

    /** @var list<int> */
    public array $completedSteps = [];

    public function mount(CurrentTeamResolver $resolver, ScopedSettings $settings): void
    {
        $team = $this->currentTeam($resolver);
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
        $this->step = min(max((int) request()->integer('step', 1), 1), 3);
    }

    public function saveProfile(ScopedSettings $settings, CurrentTeamResolver $resolver): void
    {
        $this->validate([
            'teamName' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
        ]);

        $team = $this->currentTeam($resolver);
        abort_unless((string) $team->getAttribute('user_id') === (string) auth()->id(), 403, 'Only the team owner can change team settings.');
        $team->forceFill(['name' => trim($this->teamName)])->save();
        $this->teamName = (string) $team->getAttribute('name');
        $this->persist($settings, $team, 1, ['team_name' => $team->getAttribute('name'), 'timezone' => $this->timezone]);
        $this->step = 2;
    }

    public function saveIntegrations(ScopedSettings $settings, CurrentTeamResolver $resolver): void
    {
        $this->validate([
            'githubClientId' => ['nullable', 'string', 'max:255'],
            'githubClientSecret' => ['nullable', 'string', 'max:1000'],
            'googleClientId' => ['nullable', 'string', 'max:255'],
            'googleClientSecret' => ['nullable', 'string', 'max:1000'],
            'stripeSecret' => ['nullable', 'string', 'max:1000'],
        ]);

        $team = $this->currentTeam($resolver);
        abort_unless((string) $team->getAttribute('user_id') === (string) auth()->id(), 403, 'Only the team owner can change team settings.');
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

        $this->persist($settings, $team, 2, ['integrations' => $integrations]);
        $this->githubClientSecret = '';
        $this->googleClientSecret = '';
        $this->stripeSecret = '';
        $this->step = 3;
    }

    public function finish(ScopedSettings $settings, CurrentTeamResolver $resolver): void
    {
        $team = $this->currentTeam($resolver);
        $this->persist($settings, $team, 3, []);
        $this->redirect(Dashboard::getUrl(panel: 'app'));
    }

    public function goToStep(int $step): void
    {
        if ($step < 1 || $step > 3 || ($step > 1 && ! in_array($step - 1, $this->completedSteps, true))) {
            return;
        }

        $this->step = $step;
    }

    public function securityUrl(): string
    {
        return AccountSecurity::getUrl(panel: 'app');
    }

    /** @return array<string, mixed> */
    public function integrationStatus(): array
    {
        $teamId = auth()->user()?->current_team_id;
        if ($teamId === null) {
            return [];
        }

        $integrations = app(ScopedSettings::class)->resolve('team.setup', ['team' => $teamId], ['integrations' => []])['integrations'] ?? [];

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

        return $team;
    }

    /** @param array<string, mixed> $changes */
    private function persist(ScopedSettings $settings, Team $team, int $step, array $changes): void
    {
        $existing = $settings->resolve('team.setup', ['team' => $team->getKey()], [
            'completed_steps' => [],
            'team_name' => (string) $team->getAttribute('name'),
            'timezone' => $this->timezone,
            'integrations' => [],
        ]);
        $completed = array_values(array_unique([...array_map('intval', $existing['completed_steps'] ?? []), $step]));
        $settings->put(new TeamSetupDefinition(), 'team', (string) $team->getKey(), array_replace_recursive($existing, $changes, ['completed_steps' => $completed]));
        $this->completedSteps = $completed;
    }
}
