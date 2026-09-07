<?php

declare(strict_types=1);

namespace Liberu\ControlPanel\WebHosting;

use Illuminate\Support\ServiceProvider;
use Liberu\ControlPanel\WebHosting\Actions\ActivateDomain;
use Liberu\ControlPanel\WebHosting\Actions\AddDirectoryProtectionUser;
use Liberu\ControlPanel\WebHosting\Actions\ArchiveDomain;
use Liberu\ControlPanel\WebHosting\Actions\ConfigureHotlinkProtection;
use Liberu\ControlPanel\WebHosting\Actions\CreateCronJob;
use Liberu\ControlPanel\WebHosting\Actions\CreateDirectoryProtection;
use Liberu\ControlPanel\WebHosting\Actions\CreateDomain;
use Liberu\ControlPanel\WebHosting\Actions\CreateMimeType;
use Liberu\ControlPanel\WebHosting\Actions\CreateRedirect;
use Liberu\ControlPanel\WebHosting\Actions\CreateSubdomain;
use Liberu\ControlPanel\WebHosting\Actions\CreateVirtualHost;
use Liberu\ControlPanel\WebHosting\Actions\CreateWebsiteLaunch;
use Liberu\ControlPanel\WebHosting\Actions\CreateWordPressOperation;
use Liberu\ControlPanel\WebHosting\Actions\DeleteCronJob;
use Liberu\ControlPanel\WebHosting\Actions\DeleteCustomErrorPage;
use Liberu\ControlPanel\WebHosting\Actions\DeleteDirectoryProtection;
use Liberu\ControlPanel\WebHosting\Actions\DeleteHostedApplication;
use Liberu\ControlPanel\WebHosting\Actions\DeleteRedirect;
use Liberu\ControlPanel\WebHosting\Actions\DeleteSubdomain;
use Liberu\ControlPanel\WebHosting\Actions\DeleteVirtualHost;
use Liberu\ControlPanel\WebHosting\Actions\RecordCronExecution;
use Liberu\ControlPanel\WebHosting\Actions\RecordResourceUsage;
use Liberu\ControlPanel\WebHosting\Actions\RegisterGitDeployment;
use Liberu\ControlPanel\WebHosting\Actions\RegisterHostingResource;
use Liberu\ControlPanel\WebHosting\Actions\RemoveDirectoryProtectionUser;
use Liberu\ControlPanel\WebHosting\Actions\RequestCertificate;
use Liberu\ControlPanel\WebHosting\Actions\RunWebsiteLaunch;
use Liberu\ControlPanel\WebHosting\Actions\RunWordPressOperation;
use Liberu\ControlPanel\WebHosting\Actions\SaveCustomErrorPage;
use Liberu\ControlPanel\WebHosting\Actions\SavePhpConfiguration;
use Liberu\ControlPanel\WebHosting\Actions\SuspendDomain;
use Liberu\ControlPanel\WebHosting\Actions\UpdateCronJob;
use Liberu\ControlPanel\WebHosting\Actions\UpdateRedirect;
use Liberu\ControlPanel\WebHosting\Actions\UpdateSubdomain;
use Liberu\ControlPanel\WebHosting\Contracts\WebsiteLaunchExecutor;
use Liberu\ControlPanel\WebHosting\Contracts\WordPressLifecycleExecutor;
use Liberu\ControlPanel\WebHosting\Queries\ListDomains;
use Liberu\ControlPanel\WebHosting\Queries\ListGitDeployments;
use Liberu\ControlPanel\WebHosting\Queries\ListResourceUsage;
use Liberu\ControlPanel\WebHosting\Services\UnavailableWebsiteLaunchExecutor;
use Liberu\ControlPanel\WebHosting\Services\UnavailableWordPressLifecycleExecutor;

final class WebHostingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(WebsiteLaunchExecutor::class, UnavailableWebsiteLaunchExecutor::class);
        $this->app->scoped(WordPressLifecycleExecutor::class, UnavailableWordPressLifecycleExecutor::class);
        $this->app->scoped(AddDirectoryProtectionUser::class);
        $this->app->scoped(ConfigureHotlinkProtection::class);
        $this->app->scoped(CreateDirectoryProtection::class);
        $this->app->scoped(DeleteCustomErrorPage::class);
        $this->app->scoped(DeleteDirectoryProtection::class);
        $this->app->scoped(RemoveDirectoryProtectionUser::class);
        $this->app->scoped(SaveCustomErrorPage::class);
        $this->app->scoped(ActivateDomain::class);
        $this->app->scoped(ArchiveDomain::class);
        $this->app->scoped(CreateDomain::class);
        $this->app->scoped(CreateCronJob::class);
        $this->app->scoped(UpdateCronJob::class);
        $this->app->scoped(DeleteCronJob::class);
        $this->app->scoped(RecordCronExecution::class);
        $this->app->scoped(CreateMimeType::class);
        $this->app->scoped(CreateVirtualHost::class);
        $this->app->scoped(DeleteHostedApplication::class);
        $this->app->scoped(DeleteRedirect::class);
        $this->app->scoped(DeleteVirtualHost::class);
        $this->app->scoped(CreateSubdomain::class);
        $this->app->scoped(UpdateSubdomain::class);
        $this->app->scoped(DeleteSubdomain::class);
        $this->app->scoped(ListDomains::class);
        $this->app->scoped(CreateRedirect::class);
        $this->app->scoped(RequestCertificate::class);
        $this->app->scoped(RegisterHostingResource::class);
        $this->app->scoped(RegisterGitDeployment::class);
        $this->app->scoped(ListGitDeployments::class);
        $this->app->scoped(ListResourceUsage::class);
        $this->app->scoped(RecordResourceUsage::class);
        $this->app->scoped(SavePhpConfiguration::class);
        $this->app->scoped(SuspendDomain::class);
        $this->app->scoped(UpdateRedirect::class);
        $this->app->scoped(CreateWebsiteLaunch::class);
        $this->app->scoped(RunWebsiteLaunch::class);
        $this->app->scoped(CreateWordPressOperation::class);
        $this->app->scoped(RunWordPressOperation::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
