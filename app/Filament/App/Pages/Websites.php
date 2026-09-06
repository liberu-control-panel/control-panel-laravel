<?php

namespace App\Filament\App\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Liberu\ControlPanel\WebHosting\Enums\DomainStatus;
use Liberu\ControlPanel\WebHosting\Models\Domain;
use Liberu\ControlPanel\WebHosting\Models\SslCertificate;
use Liberu\ControlPanel\WebHosting\WebHostingServiceProvider;
use Liberu\ControlPanel\WebHostingFilament\Resources\DomainResource;
use Liberu\Foundation\Organizations\Models\Team;
use Liberu\Foundation\Organizations\Services\CurrentTeamResolver;

class Websites extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Web Hosting';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.pages.websites';

    protected ?bool $canManageWebsites = null;

    public static function canAccess(): bool
    {
        return auth()->check()
            && app()->providerIsLoaded(WebHostingServiceProvider::class)
            && static::currentTeam() !== null
            && DomainResource::canViewAny();
    }

    protected static function currentTeam(): ?Team
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $teamId = Filament::getCurrentPanel()?->getId() === 'admin'
            ? Filament::getTenant()?->getKey()
            : $user->current_team_id;

        return app(CurrentTeamResolver::class)->resolve($user, $teamId);
    }

    public function getSubheading(): string
    {
        return 'Review hosting configuration, certificate expiry and deployment failures for your active team. These records do not verify live website availability.';
    }

    /** @return Builder<Domain> */
    protected function websitesQuery(): Builder
    {
        abort_unless(static::canAccess(), 403);
        $teamId = (string) static::currentTeam()->getKey();

        return Domain::query()
            ->where('team_id', $teamId)
            ->select(['id', 'team_id', 'account_id', 'hostname', 'status', 'created_at'])
            ->withCount([
                'virtualHosts as active_hosts_count' => fn (Builder $query) => $query->where('active', true),
                'gitDeployments as failed_deployments_count' => fn (Builder $query) => $query->where('team_id', $teamId)->where('status', 'failed'),
            ])
            ->addSelect(['certificate_expires_at' => SslCertificate::query()
                ->selectRaw('MAX(expires_at)')
                ->whereColumn('domain_id', 'control_panel_domains.id')
                ->where('team_id', $teamId)
                ->where('status', 'issued')]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->websitesQuery())
            ->columns([
                TextColumn::make('hostname')->label('Website')->searchable()->sortable()->wrap(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (DomainStatus $state): string => ucfirst($state->value))
                    ->color(fn (DomainStatus $state): string => match ($state) {
                        DomainStatus::Active => 'success',
                        DomainStatus::Suspended => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('active_hosts_count')->label('Active host configurations')->numeric()->sortable(),
                TextColumn::make('certificate_expires_at')->label('Issued certificate expires')->dateTime()->sortable()
                    ->placeholder('Not recorded'),
                TextColumn::make('certificate_guidance')->label('Certificate guidance')->wrap()
                    ->state(fn (Domain $record): string => $this->certificateSummary($record)),
                TextColumn::make('failed_deployments_count')->label('Failed deployments')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(DomainStatus::cases())->mapWithKeys(
                    fn (DomainStatus $status): array => [$status->value => ucfirst($status->value)],
                )->all()),
                Filter::make('needs_attention')->label('Needs attention')->query(function (Builder $query): Builder {
                    $teamId = (string) static::currentTeam()?->getKey();

                    return $query->where(fn (Builder $query) => $query
                        ->where('status', '!=', DomainStatus::Active->value)
                        ->orWhereDoesntHave('virtualHosts', fn (Builder $hosts) => $hosts->where('active', true))
                        ->orWhereHas('gitDeployments', fn (Builder $deployments) => $deployments->where('team_id', $teamId)->where('status', 'failed'))
                        ->orWhereNotIn('id', SslCertificate::query()->select('domain_id')
                            ->where('team_id', $teamId)->where('status', 'issued')->where('expires_at', '>', now()->addDays(30))));
                }),
            ])
            ->recordActions([
                Action::make('manage')->label('Manage website')->icon('heroicon-o-pencil-square')
                    ->visible(fn (Domain $record): bool => $this->canManageWebsites() && DomainResource::canEdit($record))
                    ->url(fn (Domain $record): ?string => $this->canManageWebsites()
                        ? DomainResource::getUrl('edit', ['record' => $record]) : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->emptyStateHeading('No websites found')
            ->emptyStateDescription('Add a domain to start hosting, or clear your search and filters. If you cannot add domains, ask your team administrator.')
            ->emptyStateIcon('heroicon-o-globe-alt');
    }

    protected function certificateSummary(Domain $record): string
    {
        $expiry = $record->getAttribute('certificate_expires_at');

        return match (true) {
            $expiry === null => 'Request or record an issued certificate.',
            $expiry <= now()->toDateTimeString() => 'Expired — renew the certificate.',
            $expiry <= now()->addDays(30)->toDateTimeString() => 'Expires within 30 days — check renewal.',
            default => 'Expiry recorded; deployment and auto-renewal are not verified.',
        };
    }

    protected function canManageWebsites(): bool
    {
        return $this->canManageWebsites ??= in_array(DomainResource::class, Filament::getCurrentPanel()?->getResources() ?? [], true)
            && (string) auth()->user()?->current_team_id === (string) static::currentTeam()?->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addWebsite')->label('Add website')->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->canManageWebsites() && DomainResource::canCreate())
                ->url(fn (): ?string => $this->canManageWebsites() ? DomainResource::getUrl('create') : null),
        ];
    }
}
