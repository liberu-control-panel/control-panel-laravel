<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\AccountSetup;
use App\Filament\App\Widgets\AccountSetupWidget;
use App\Filament\ModulePlugins;
use App\Support\ThemeColors;
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

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->path('app')
            ->colors(app(ThemeColors::class)->forSite())
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->pages([
                Dashboard::class,
                AccountSetup::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Getting started'),
                NavigationGroup::make('Workspace'),
                NavigationGroup::make('Account & Security'),
                NavigationGroup::make('Preferences'),
                NavigationGroup::make('Accounts & Hosting')->collapsed(),
                NavigationGroup::make('Web Hosting')->collapsed(),
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
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            ->widgets([
                AccountSetupWidget::class,
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
            ->plugins(app(ModulePlugins::class)->forPanel('app'))
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
