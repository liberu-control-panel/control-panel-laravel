<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Filament\App\Pages\AccountSetup;
use Filament\Widgets\Widget;
use Liberu\Foundation\Organizations\Services\CurrentTeamResolver;
use Liberu\Foundation\Settings\Services\ScopedSettings;

final class AccountSetupWidget extends Widget
{
    protected string $view = 'filament.app.widgets.account-setup-widget';

    protected int|string|array $columnSpan = 'full';

    public bool $needsSetup = false;

    public static function canView(): bool
    {
        return AccountSetup::canAccess();
    }

    public function mount(CurrentTeamResolver $resolver, ScopedSettings $settings): void
    {
        $user = auth()->user();
        $team = $user === null ? null : $resolver->resolve($user, $user->current_team_id);
        if ($team === null || (string) $team->getAttribute('user_id') !== (string) $user->getAuthIdentifier()) {
            return;
        }

        $stored = $settings->resolve('team.setup', ['team' => $team->getKey()], ['completed_steps' => []]);
        $this->needsSetup = array_diff([1, 2, 3], array_map('intval', $stored['completed_steps'] ?? [])) !== [];
    }

    public function setupUrl(): string
    {
        return AccountSetup::getUrl(panel: 'app');
    }
}
