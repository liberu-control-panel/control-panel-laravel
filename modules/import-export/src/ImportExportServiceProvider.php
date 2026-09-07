<?php

namespace Liberu\Foundation\ImportExport;

use Illuminate\Support\ServiceProvider;
use Liberu\Foundation\ImportExport\Actions\CreateHostingMigration;
use Liberu\Foundation\ImportExport\Actions\RunHostingMigration;
use Liberu\Foundation\ImportExport\Contracts\HostingMigrationExecutor;
use Liberu\Foundation\ImportExport\Contracts\MigrationDatabaseConnection;
use Liberu\Foundation\ImportExport\Contracts\MigrationDnsRecordWriter;
use Liberu\Foundation\ImportExport\Contracts\MigrationMailboxWriter;
use Liberu\Foundation\ImportExport\Services\BindDnsMigrationExecutor;
use Liberu\Foundation\ImportExport\Services\HostingMigrationArchive;
use Liberu\Foundation\ImportExport\Services\MboxMailMigrationExecutor;
use Liberu\Foundation\ImportExport\Services\SqlDatabaseMigrationExecutor;
use Liberu\Foundation\ImportExport\Services\UnavailableHostingMigrationExecutor;
use Liberu\Foundation\ImportExport\Services\UnavailableMigrationDatabaseConnection;
use Liberu\Foundation\ImportExport\Services\UnavailableMigrationDnsRecordWriter;
use Liberu\Foundation\ImportExport\Services\UnavailableMigrationMailboxWriter;

final class ImportExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(HostingMigrationArchive::class);
        $this->app->scoped(HostingMigrationExecutor::class, UnavailableHostingMigrationExecutor::class);
        $this->app->scoped(MigrationDatabaseConnection::class, UnavailableMigrationDatabaseConnection::class);
        $this->app->scoped(MigrationDnsRecordWriter::class, UnavailableMigrationDnsRecordWriter::class);
        $this->app->scoped(MigrationMailboxWriter::class, UnavailableMigrationMailboxWriter::class);
        $this->app->scoped(CreateHostingMigration::class);
        $this->app->scoped(RunHostingMigration::class);
        $this->app->scoped(SqlDatabaseMigrationExecutor::class);
        $this->app->scoped(BindDnsMigrationExecutor::class);
        $this->app->scoped(MboxMailMigrationExecutor::class);
    }

    public function boot(): void
    {
        if (class_exists('App\\Hosting\\Migrations\\ControlPanelDnsRecordWriter')) {
            $this->app->scoped(MigrationDnsRecordWriter::class, 'App\\Hosting\\Migrations\\ControlPanelDnsRecordWriter');
        }
        if (class_exists('App\\Hosting\\Migrations\\ControlPanelMailboxWriter')) {
            $this->app->scoped(MigrationMailboxWriter::class, 'App\\Hosting\\Migrations\\ControlPanelMailboxWriter');
        }
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
