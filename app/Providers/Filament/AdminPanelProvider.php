<?php

namespace App\Providers\Filament;

use App\Filament\ModulePlugins;
use App\Support\ThemeColors;
use BezhanSalleh\FilamentShield\Middleware\SyncShieldTenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Liberu\Foundation\ApplicationCore\Http\Middleware\SecurityHeaders;
use Liberu\Foundation\Localization\Http\Middleware\SetLocale;
use Liberu\Foundation\Organizations\Models\Team;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors(app(ThemeColors::class)->forSite())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Workspace'),
                NavigationGroup::make('Accounts & Hosting'),
                NavigationGroup::make('Web Hosting'),
                NavigationGroup::make('Files & Access')->collapsed(),
                NavigationGroup::make('Databases')->collapsed(),
                NavigationGroup::make('DNS')->collapsed(),
                NavigationGroup::make('Email & Messaging')->collapsed(),
                NavigationGroup::make('Certificates')->collapsed(),
                NavigationGroup::make('Backups')->collapsed(),
                NavigationGroup::make('Monitoring')->collapsed(),
                NavigationGroup::make('Automation & Integrations')->collapsed(),
                NavigationGroup::make('Containers')->collapsed(),
                NavigationGroup::make('Kubernetes')->collapsed(),
                NavigationGroup::make('Server Management')->collapsed(),
                NavigationGroup::make('Operations')->collapsed(),
                NavigationGroup::make('Security & Compliance')->collapsed(),
                NavigationGroup::make('Administration')->collapsed(),
                NavigationGroup::make('Settings')->collapsed(),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->tenant(Team::class, ownershipRelationship: 'team')
            ->tenantMiddleware([
                SyncShieldTenant::class,
            ], isPersistent: true)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
                SecurityHeaders::class,
            ])
            ->plugins(app(ModulePlugins::class)->forPanel('admin'))
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
