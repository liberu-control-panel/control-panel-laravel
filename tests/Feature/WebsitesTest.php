<?php

use App\Filament\App\Pages\Websites;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Liberu\ControlPanel\WebHosting\Models\Domain;
use Liberu\ControlPanel\WebHosting\Models\GitDeployment;
use Liberu\ControlPanel\WebHosting\Models\SslCertificate;
use Liberu\ControlPanel\WebHosting\WebHostingServiceProvider;
use Liberu\ControlPanel\WebHostingFilament\Resources\DomainResource;
use Liberu\Foundation\Organizations\Models\Team;
use Livewire\Livewire;

beforeEach(function (): void {
    app()->register(WebHostingServiceProvider::class);
    $this->artisan('migrate');
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant(null);
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create(['user_id' => $this->user->getKey()]);
    $this->user->forceFill(['current_team_id' => $this->team->getKey()])->save();
    $this->actingAs($this->user);
});

function websiteForTeam(Team $team, string $hostname = 'example.test'): Domain
{
    return Domain::query()->create(['team_id' => $team->getKey(), 'hostname' => $hostname, 'status' => 'active']);
}

it('shows only the current team websites and supports searching', function (): void {
    $mine = websiteForTeam($this->team);
    $other = websiteForTeam(Team::factory()->create(), 'private.test');
    $another = websiteForTeam($this->team, 'another.test');

    Livewire::test(Websites::class)
        ->assertCanSeeTableRecords([$mine, $another])
        ->assertCanNotSeeTableRecords([$other])
        ->searchTable('example.test')
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$another, $other]);
});

it('shows certificate guidance without treating a stored record as live health', function (?string $expiry, string $status, string $message): void {
    $domain = websiteForTeam($this->team);
    SslCertificate::query()->create([
        'team_id' => $this->team->getKey(), 'domain_id' => $domain->getKey(),
        'issuer' => 'test', 'status' => $status,
        'expires_at' => $expiry === null ? null : now()->modify($expiry),
    ]);

    Livewire::test(Websites::class)->assertSee($message);
})->with([
    [null, 'issued', 'Request or record an issued certificate.'],
    ['-1 day', 'issued', 'Expired — renew the certificate.'],
    ['+10 days', 'issued', 'Expires within 30 days — check renewal.'],
    ['+60 days', 'issued', 'Expiry recorded; deployment and auto-renewal are not verified.'],
    ['+60 days', 'revoked', 'Request or record an issued certificate.'],
]);

it('filters sites needing attention using configuration, expiry and deployment state', function (): void {
    $ready = websiteForTeam($this->team, 'configured.test');
    $ready->virtualHosts()->create(['server' => 'nginx', 'document_root' => '/srv/site', 'active' => true]);
    SslCertificate::query()->create([
        'team_id' => $this->team->getKey(), 'domain_id' => $ready->getKey(),
        'issuer' => 'test', 'status' => 'issued', 'expires_at' => now()->addDays(60),
    ]);
    $pending = websiteForTeam($this->team, 'pending.test');
    $pending->update(['status' => 'pending']);

    Livewire::test(Websites::class)->filterTable('needs_attention')
        ->assertCanSeeTableRecords([$pending])->assertCanNotSeeTableRecords([$ready]);

    GitDeployment::query()->create([
        'team_id' => $this->team->getKey(), 'domain_id' => $ready->getKey(),
        'repository_url' => 'https://example.test/repo.git', 'repository_type' => 'other',
        'deploy_path' => '/srv/site', 'status' => 'failed',
    ]);
    Livewire::test(Websites::class)->filterTable('needs_attention')->assertCanSeeTableRecords([$ready, $pending]);
    Livewire::test(Websites::class)->filterTable('status', 'pending')->assertCanSeeTableRecords([$pending])->assertCanNotSeeTableRecords([$ready]);
});

it('does not use a foreign team certificate to clear an alert', function (): void {
    $domain = websiteForTeam($this->team);
    SslCertificate::query()->create([
        'team_id' => Team::factory()->create()->getKey(), 'domain_id' => $domain->getKey(),
        'issuer' => 'test', 'status' => 'issued', 'expires_at' => now()->addDays(60),
    ]);
    Livewire::test(Websites::class)->assertSee('Request or record an issued certificate.')
        ->filterTable('needs_attention')->assertCanSeeTableRecords([$domain]);
});

it('rejects a forged or missing current team', function (bool $missing): void {
    $this->user->forceFill(['current_team_id' => $missing ? null : Team::factory()->create()->getKey()])->save();
    Livewire::test(Websites::class)->assertForbidden();
})->with([true, false]);

it('rechecks membership on subsequent requests', function (): void {
    $component = Livewire::test(Websites::class);
    $this->team->update(['status' => 'inactive']);
    $component->call('$refresh')->assertForbidden();
});

it('respects the domain view policy', function (): void {
    Gate::before(fn (): bool => false);
    Livewire::test(Websites::class)->assertForbidden();
});

it('uses the admin tenant instead of the user current team', function (): void {
    $current = websiteForTeam($this->team);
    $selected = Team::factory()->create(['user_id' => $this->user->getKey()]);
    $domain = websiteForTeam($selected, 'selected.test');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($selected);

    Livewire::test(Websites::class)->assertCanSeeTableRecords([$domain])->assertCanNotSeeTableRecords([$current]);
});

it('has a bounded query count as website rows grow', function (): void {
    websiteForTeam($this->team);
    Livewire::test(Websites::class);
    DB::enableQueryLog();
    DB::flushQueryLog();
    Livewire::test(Websites::class);
    $singleCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    foreach (range(1, 9) as $index) {
        websiteForTeam($this->team, "site-{$index}.test");
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    Livewire::test(Websites::class);
    $multipleCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($multipleCount)->toBeLessThanOrEqual($singleCount);
});

it('hides management actions when the app has no domain management resource', function (): void {
    $domain = websiteForTeam($this->team);
    Livewire::test(Websites::class)->assertActionHidden('addWebsite')
        ->assertTableActionHidden('manage', $domain);
});

it('offers policy-aware management links when the resource and team context agree', function (): void {
    $domain = websiteForTeam($this->team);
    $panel = Filament::getPanel('admin');
    $panel->resources([DomainResource::class]);
    Filament::setCurrentPanel($panel);
    Filament::setTenant($this->team);
    Route::get('/admin/{tenant}/domains/create', fn () => 'create')->name('filament.admin.resources.domains.create');
    Route::get('/admin/{tenant}/domains/{record}/edit', fn () => 'edit')->name('filament.admin.resources.domains.edit');

    Livewire::test(Websites::class)->assertActionVisible('addWebsite')
        ->assertTableActionVisible('manage', $domain)
        ->assertSee(DomainResource::getUrl('edit', ['record' => $domain]));
});

it('requires authentication', function (): void {
    auth()->logout();
    expect(Websites::canAccess())->toBeFalse();
    Livewire::test(Websites::class)->assertForbidden();
});
