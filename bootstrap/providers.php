<?php

use App\Hosting\Migrations\HostingMigrationBindingsServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\HostingMigrationServiceProvider;
use Liberu\Foundation\ModuleManager\ModuleManagerServiceProvider;

return [
    ModuleManagerServiceProvider::class,
    HostingMigrationServiceProvider::class,
    HostingMigrationBindingsServiceProvider::class,
    AdminPanelProvider::class,
    AppPanelProvider::class,
];
