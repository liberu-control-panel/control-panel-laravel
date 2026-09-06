<?php

use App\Filament\App\Pages\AccountSetup;
use App\Filament\App\Widgets\AccountSetupWidget;
use App\Models\User;
use App\Settings\TeamSetupDefinition;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Liberu\Foundation\Organizations\Models\Team;
use Liberu\Foundation\Settings\Services\ScopedSettings;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

it('saves the current team profile and resumes at integrations', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->getKey(), 'name' => 'New workspace']);
    $user->forceFill(['current_team_id' => $team->getKey()])->save();

    Livewire::actingAs($user)
        ->test(AccountSetup::class)
        ->set('teamName', 'Launch workspace')
        ->set('timezone', 'Europe/London')
        ->call('saveProfile')
        ->assertSet('step', 2)
        ->assertHasNoErrors();

    expect($team->refresh()->name)->toBe('Launch workspace')
        ->and(app(ScopedSettings::class)->resolve('team.setup', ['team' => $team->getKey()])['timezone'])->toBe('Europe/London');
});

it('encrypts saved integration credentials and clears secret inputs', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->getKey()]);
    $user->forceFill(['current_team_id' => $team->getKey()])->save();

    Livewire::actingAs($user)
        ->test(AccountSetup::class)
        ->set('githubClientId', 'github-client')
        ->call('saveProfile')
        ->set('githubClientSecret', 'github-secret')
        ->set('stripeSecret', 'stripe-secret')
        ->call('saveIntegrations')
        ->assertSet('step', 3)
        ->assertSet('githubClientSecret', '')
        ->assertSet('stripeSecret', '')
        ->assertHasNoErrors();

    $stored = DB::table('scoped_settings')->where('scope_type', 'team')->where('scope_id', (string) $team->getKey())->where('key', 'team.setup')->value('value');

    expect($stored)->not->toContain('github-secret')->not->toContain('stripe-secret')
        ->and(app(ScopedSettings::class)->resolve('team.setup', ['team' => $team->getKey()])['integrations']['github_client_secret'])->toBe('github-secret');
});

function setupOwner(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->getKey()]);
    $user->forceFill(['current_team_id' => $team->getKey()])->save();

    return [$user, $team];
}

it('resumes from persisted progress and clamps query-string step skipping', function (): void {
    [$user] = setupOwner();
    Livewire::actingAs($user)->withQueryParams(['step' => 3])->test(AccountSetup::class)->assertSet('step', 1)
        ->call('saveProfile')->assertSet('step', 2);
    Livewire::withQueryParams([])->test(AccountSetup::class)->assertSet('step', 2);
});

it('requires persisted preceding steps for all forward actions', function (string $action, array $parameters): void {
    [$user] = setupOwner();
    Livewire::actingAs($user)->test(AccountSetup::class)->call($action, ...$parameters)
        ->assertHasErrors(['setup'])->assertSet('completedSteps', []);
})->with([
    ['saveIntegrations', []], ['finish', []], ['goToStep', [2]], ['goToStep', [3]],
]);

it('locks progress and team context against client updates', function (string $field, mixed $value): void {
    [$user] = setupOwner();
    $component = Livewire::actingAs($user)->test(AccountSetup::class);
    $this->expectException(CannotUpdateLockedPropertyException::class);
    $component->set($field, $value);
})->with([
    ['step', 3], ['completedSteps', [1, 2, 3]], ['teamId', 'another-team'], ['revision', 99],
]);

it('rejects non-owner members even when they have an active team membership', function (): void {
    [$owner, $team] = setupOwner();
    $member = User::factory()->create(['current_team_id' => $team->getKey()]);
    $team->users()->attach($member, ['role' => 'admin', 'status' => 'active']);
    Livewire::actingAs($member)->test(AccountSetup::class)->assertForbidden();
});

it('rejects team switching between opening the wizard and saving', function (): void {
    [$user, $original] = setupOwner();
    $other = Team::factory()->create(['user_id' => $user->getKey(), 'name' => 'Other team']);
    $component = Livewire::actingAs($user)->test(AccountSetup::class)->set('teamName', 'Stale draft');
    $user->forceFill(['current_team_id' => $other->getKey()])->save();
    $component->call('saveProfile')->assertStatus(409);
    expect($other->refresh()->getAttribute('name'))->toBe('Other team')
        ->and($original->refresh()->getAttribute('name'))->not->toBe('Stale draft');
});

it('rejects stale tabs without overwriting newer settings or team names', function (): void {
    [$user, $team] = setupOwner();
    $first = Livewire::actingAs($user)->test(AccountSetup::class);
    $second = Livewire::test(AccountSetup::class);
    $first->set('teamName', 'First saved')->call('saveProfile');
    $second->set('teamName', 'Stale second')->call('saveProfile')->assertStatus(409);
    expect($team->refresh()->getAttribute('name'))->toBe('First saved');
});

it('rechecks team ownership after the wizard is opened', function (): void {
    [$user, $team] = setupOwner();
    $component = Livewire::actingAs($user)->test(AccountSetup::class);
    $team->forceFill(['user_id' => User::factory()->create()->getKey()])->save();
    $component->call('finish')->assertForbidden();
});

it('rejects whitespace-only team names before any changes', function (): void {
    [$user, $team] = setupOwner();
    $name = $team->getAttribute('name');
    Livewire::actingAs($user)->test(AccountSetup::class)->set('teamName', '   ')->call('saveProfile')->assertHasErrors(['teamName']);
    expect($team->refresh()->getAttribute('name'))->toBe($name);
});

it('requires complete OAuth credential pairs', function (string $field, string $error): void {
    [$user] = setupOwner();
    Livewire::actingAs($user)->test(AccountSetup::class)->call('saveProfile')
        ->set($field, 'credential')->call('saveIntegrations')->assertHasErrors([$error]);
})->with([
    ['githubClientId', 'githubClientSecret'], ['githubClientSecret', 'githubClientSecret'],
    ['googleClientId', 'googleClientSecret'], ['googleClientSecret', 'googleClientSecret'],
]);

it('preserves blank secrets and requires a matching new secret when changing OAuth client IDs', function (): void {
    [$user, $team] = setupOwner();
    Livewire::actingAs($user)->test(AccountSetup::class)->call('saveProfile')
        ->set('githubClientId', 'old-id')->set('githubClientSecret', 'old-secret')->call('saveIntegrations');
    $component = Livewire::test(AccountSetup::class)->assertSet('githubClientSecret', '')
        ->assertDontSee('old-secret')->call('goToStep', 2)->call('saveIntegrations')->assertHasNoErrors();
    expect(app(ScopedSettings::class)->resolve('team.setup', ['team' => $team->getKey()])['integrations']['github_client_secret'])->toBe('old-secret');
    $component->call('goToStep', 2)->set('githubClientId', 'new-id')->call('saveIntegrations')
        ->assertHasErrors(['githubClientSecret'])
        ->set('githubClientSecret', 'new-secret')->call('saveIntegrations')->assertHasNoErrors();
    expect(app(ScopedSettings::class)->resolve('team.setup', ['team' => $team->getKey()])['integrations']['github_client_secret'])->toBe('new-secret');
});

it('allows optional integrations to be skipped without claiming they are connected', function (): void {
    [$user, $team] = setupOwner();
    Livewire::actingAs($user)->test(AccountSetup::class)->call('saveProfile')->call('saveIntegrations')
        ->assertSee('Skipped — no credentials stored')->assertSee('not infrastructure provisioning')
        ->call('finish')->assertRedirect();
    expect(app(ScopedSettings::class)->resolve('team.setup', ['team' => $team->getKey()])['completed_steps'])->toBe([1, 2, 3]);
});

it('invalidates later completion when the workspace profile changes', function (): void {
    [$user] = setupOwner();
    Livewire::actingAs($user)->test(AccountSetup::class)->call('saveProfile')->call('saveIntegrations')->call('finish');
    Livewire::test(AccountSetup::class)->call('goToStep', 1)->call('saveProfile')
        ->assertSet('completedSteps', [1])->call('finish')->assertHasErrors(['setup']);
});

it('reviews the saved profile rather than unsaved edits', function (): void {
    [$user, $team] = setupOwner();
    Livewire::actingAs($user)->test(AccountSetup::class)->call('saveProfile')->call('saveIntegrations')
        ->call('goToStep', 1)->set('teamName', 'Unsaved name')->set('timezone', 'Europe/London')
        ->call('goToStep', 3)->assertSet('teamName', $team->getAttribute('name'))->assertSet('timezone', 'UTC');
});

it('does not trust malformed legacy progress that skips the profile', function (): void {
    [$user, $team] = setupOwner();
    app(ScopedSettings::class)->put(new TeamSetupDefinition(), 'team', (string) $team->getKey(), ['completed_steps' => [2], 'integrations' => []]);
    Livewire::actingAs($user)->test(AccountSetup::class)->assertSet('step', 1)
        ->call('goToStep', 3)->assertHasErrors(['setup']);
    Livewire::test(AccountSetupWidget::class)->assertSet('needsSetup', true);
});

it('keeps the setup reminder visible unless all steps are complete', function (array $completed, bool $expected): void {
    [$user, $team] = setupOwner();
    app(ScopedSettings::class)->put(new TeamSetupDefinition(), 'team', (string) $team->getKey(), ['completed_steps' => $completed, 'integrations' => []]);
    Livewire::actingAs($user)->test(AccountSetupWidget::class)->assertSet('needsSetup', $expected);
})->with([
    [[], true], [[3], true], [[1, 3], true], [[1, 2, 3], false],
]);

it('does not offer owner-only setup to other team members', function (): void {
    [$owner, $team] = setupOwner();
    $member = User::factory()->create(['current_team_id' => $team->getKey()]);
    $team->users()->attach($member, ['role' => 'admin', 'status' => 'active']);
    $this->actingAs($member);
    expect(AccountSetupWidget::canView())->toBeFalse();
    Livewire::test(AccountSetupWidget::class)->assertSet('needsSetup', false);
});

it('rolls back scoped settings if updating the team profile fails', function (): void {
    [$user, $team] = setupOwner();
    $name = $team->getAttribute('name');
    Team::updating(function (): void {
        throw new RuntimeException('Profile update failed');
    });
    try {
        Livewire::actingAs($user)->test(AccountSetup::class)->set('teamName', 'Should roll back')->call('saveProfile');
        $this->fail('Expected the team update to fail.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Profile update failed');
    }
    expect($team->refresh()->getAttribute('name'))->toBe($name)
        ->and(app(ScopedSettings::class)->resolve('team.setup', ['team' => $team->getKey()]))->toBeNull();
});

it('resolves credential badges once per render rather than once per provider', function (): void {
    [$user] = setupOwner();
    Livewire::actingAs($user)->test(AccountSetup::class)->call('saveProfile');
    DB::enableQueryLog();
    DB::flushQueryLog();
    Livewire::test(AccountSetup::class)->assertSet('step', 2);
    $reads = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with($query['query'], 'select') && in_array('team.setup', $query['bindings'], true));
    DB::disableQueryLog();

    expect($reads)->toHaveCount(2);
});
