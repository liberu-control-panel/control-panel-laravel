<?php

declare(strict_types=1);

namespace Liberu\ControlPanel\WebHostingApi\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Liberu\ControlPanel\WebHosting\Actions\ActivateDomain;
use Liberu\ControlPanel\WebHosting\Actions\ArchiveDomain;
use Liberu\ControlPanel\WebHosting\Actions\CheckApplicationHealth;
use Liberu\ControlPanel\WebHosting\Actions\CheckWordPressUpdates;
use Liberu\ControlPanel\WebHosting\Actions\CreateCronJob;
use Liberu\ControlPanel\WebHosting\Actions\CreateDomain;
use Liberu\ControlPanel\WebHosting\Actions\CreateMimeType;
use Liberu\ControlPanel\WebHosting\Actions\CreateRedirect;
use Liberu\ControlPanel\WebHosting\Actions\CreateSubdomain;
use Liberu\ControlPanel\WebHosting\Actions\CreateVirtualHost;
use Liberu\ControlPanel\WebHosting\Actions\CreateWebsiteLaunch;
use Liberu\ControlPanel\WebHosting\Actions\CreateWordPressOperation;
use Liberu\ControlPanel\WebHosting\Actions\DeleteCronJob;
use Liberu\ControlPanel\WebHosting\Actions\DeleteHostedApplication;
use Liberu\ControlPanel\WebHosting\Actions\DeleteRedirect;
use Liberu\ControlPanel\WebHosting\Actions\DeleteSubdomain;
use Liberu\ControlPanel\WebHosting\Actions\DeleteVirtualHost;
use Liberu\ControlPanel\WebHosting\Actions\RecordCronExecution;
use Liberu\ControlPanel\WebHosting\Actions\RecordResourceUsage;
use Liberu\ControlPanel\WebHosting\Actions\RegisterGitDeployment;
use Liberu\ControlPanel\WebHosting\Actions\RegisterHostingResource;
use Liberu\ControlPanel\WebHosting\Actions\RequestCertificate;
use Liberu\ControlPanel\WebHosting\Actions\RequestGitDeployment;
use Liberu\ControlPanel\WebHosting\Actions\RunWebsiteLaunch;
use Liberu\ControlPanel\WebHosting\Actions\RunWordPressOperation;
use Liberu\ControlPanel\WebHosting\Actions\SavePhpConfiguration;
use Liberu\ControlPanel\WebHosting\Actions\SuspendDomain;
use Liberu\ControlPanel\WebHosting\Actions\UpdateCronJob;
use Liberu\ControlPanel\WebHosting\Actions\UpdateDomain;
use Liberu\ControlPanel\WebHosting\Actions\UpdateHostedApplication;
use Liberu\ControlPanel\WebHosting\Actions\UpdateRedirect;
use Liberu\ControlPanel\WebHosting\Actions\UpdateSubdomain;
use Liberu\ControlPanel\WebHosting\Actions\UpdateVirtualHost;
use Liberu\ControlPanel\WebHosting\Enums\WordPressOperationType;
use Liberu\ControlPanel\WebHosting\Models\CronExecution;
use Liberu\ControlPanel\WebHosting\Models\CronJob;
use Liberu\ControlPanel\WebHosting\Models\Domain;
use Liberu\ControlPanel\WebHosting\Models\GitDeployment;
use Liberu\ControlPanel\WebHosting\Models\HostedApplication;
use Liberu\ControlPanel\WebHosting\Models\HostingLog;
use Liberu\ControlPanel\WebHosting\Models\MimeType;
use Liberu\ControlPanel\WebHosting\Models\PhpConfiguration;
use Liberu\ControlPanel\WebHosting\Models\Redirect;
use Liberu\ControlPanel\WebHosting\Models\ResourceUsage;
use Liberu\ControlPanel\WebHosting\Models\RuntimeVersion;
use Liberu\ControlPanel\WebHosting\Models\SslCertificate;
use Liberu\ControlPanel\WebHosting\Models\Subdomain;
use Liberu\ControlPanel\WebHosting\Models\VirtualHost;
use Liberu\ControlPanel\WebHosting\Models\WebServer;
use Liberu\ControlPanel\WebHosting\Models\WebsiteLaunch;
use Liberu\ControlPanel\WebHosting\Models\WordPressOperation;
use Liberu\ControlPanel\WebHosting\Queries\ApplicationStatistics;
use Liberu\ControlPanel\WebHosting\Queries\ListDomains;
use Liberu\ControlPanel\WebHosting\Queries\ListGitDeployments;
use Liberu\ControlPanel\WebHosting\Queries\ListResourceUsage;

final class DomainController
{
    /** @var array<string, list<string>> */
    private const RESOURCE_FIELDS = [
        'runtime' => ['runtime', 'version', 'available', 'default', 'metadata'],
        'server' => ['node_id', 'server', 'version', 'status', 'metadata'],
        'log' => ['domain_id', 'kind', 'level', 'message', 'context', 'occurred_at'],
        'application' => ['domain_id', 'name', 'type', 'version', 'document_root', 'status'],
        'redirect' => ['domain_id', 'source', 'destination', 'status_code', 'active', 'source_path', 'destination_url', 'redirect_type', 'match_query_string', 'is_regex', 'priority'],
        'certificate' => ['domain_id', 'issuer', 'status', 'issued_at', 'expires_at', 'auto_renew', 'metadata'],
        'virtual-host' => ['domain_id', 'node_id', 'server', 'runtime', 'document_root', 'desired_state', 'active'],
        'mime-type' => ['domain_id', 'extension', 'mime_type', 'active'],
    ];

    public function index(Request $request, ListDomains $list): JsonResponse
    {
        $teamId = $request->user()?->current_team_id;
        abort_if($teamId === null, 403, 'A current team is required.');
        $domains = $list->execute($teamId, $request->integer('per_page', 25));

        return response()->json([
            'data' => $domains->through(static fn (Domain $domain): array => self::resource($domain)),
            'meta' => ['current_page' => $domains->currentPage(), 'per_page' => $domains->perPage(), 'total' => $domains->total()],
        ]);
    }

    public function usage(Request $request, ListResourceUsage $list): JsonResponse
    {
        $teamId = $this->teamId($request);
        $data = $request->validate([
            'domain_id' => ['sometimes', 'uuid'],
            'months' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        if (isset($data['domain_id'])) {
            Domain::query()->where('team_id', $teamId)->findOrFail($data['domain_id']);
        }

        $months = (int) ($data['months'] ?? 12);
        $usage = $list->execute($teamId, $data['domain_id'] ?? null, $months);

        return response()->json([
            'data' => $usage->map(static fn (ResourceUsage $item): array => self::usageResource($item))->values(),
            'meta' => ['months' => $months, 'total' => $usage->count()],
        ]);
    }

    public function recordUsage(Request $request, Domain $domain, RecordResourceUsage $record): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate([
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2200'],
            'disk_usage_mb' => ['sometimes', 'integer', 'min:0'],
            'bandwidth_usage_mb' => ['sometimes', 'integer', 'min:0'],
        ]);
        $usage = $record->execute($domain, array_merge($data, ['team_id' => $this->teamId($request)]));

        return response()->json(['data' => self::usageResource($usage)], 201);
    }

    public function domainUsage(Request $request, Domain $domain, ListResourceUsage $list): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate(['months' => ['sometimes', 'integer', 'min:1', 'max:60']]);
        $months = (int) ($data['months'] ?? 12);
        $usage = $list->execute($this->teamId($request), $domain->getKey(), $months);

        return response()->json([
            'data' => $usage->map(static fn (ResourceUsage $item): array => self::usageResource($item))->values(),
            'meta' => ['months' => $months, 'total' => $usage->count(), 'domain_id' => $domain->getKey()],
        ]);
    }

    public function subdomains(Request $request, Domain $domain): JsonResponse
    {
        $this->assertTeam($request, $domain);

        return response()->json(['data' => $domain->subdomains()->latest()->get()->map(static fn (Subdomain $subdomain): array => self::subdomainResource($subdomain))]);
    }

    public function createSubdomain(Request $request, Domain $domain, CreateSubdomain $create): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate([
            'subdomain' => ['required', 'string', 'max:253'], 'document_root' => ['required', 'string', 'starts_with:/', 'max:2048'],
            'php_version' => ['nullable', 'string', 'max:40'], 'active' => ['sometimes', 'boolean'],
            'redirect_url' => ['nullable', 'url', 'max:2048'], 'redirect_type' => ['nullable', 'integer', 'in:301,302'],
        ]);

        return response()->json(['data' => self::subdomainResource($create->execute($domain, $data))], 201);
    }

    public function updateSubdomain(Request $request, string $subdomain, UpdateSubdomain $update): JsonResponse
    {
        $item = Subdomain::query()->whereKey($subdomain)->whereHas('domain', fn (Builder $query) => $query->where('team_id', $this->teamId($request)))->firstOrFail();
        $data = $request->validate([
            'document_root' => ['sometimes', 'string', 'starts_with:/', 'max:2048'], 'php_version' => ['nullable', 'string', 'max:40'],
            'active' => ['sometimes', 'boolean'], 'redirect_url' => ['nullable', 'url', 'max:2048'], 'redirect_type' => ['nullable', 'integer', 'in:301,302'],
        ]);

        return response()->json(['data' => self::subdomainResource($update->execute($item, $data))]);
    }

    public function deleteSubdomain(Request $request, string $subdomain, DeleteSubdomain $delete): JsonResponse
    {
        $item = Subdomain::query()->whereKey($subdomain)->whereHas('domain', fn (Builder $query) => $query->where('team_id', $this->teamId($request)))->firstOrFail();
        $delete->execute($item);

        return response()->json(status: 204);
    }

    public function cronJobs(Request $request, Domain $domain): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $jobs = $domain->cronJobs()->latest()->get();

        return response()->json(['data' => $jobs->map(static fn (CronJob $job): array => self::cronJobResource($job))]);
    }

    public function createCronJob(Request $request, Domain $domain, CreateCronJob $create): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'command' => ['required', 'string', 'max:4096'],
            'schedule' => ['required', 'string', 'max:100'], 'active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => self::cronJobResource($create->execute($domain, $data))], 201);
    }

    public function updateCronJob(Request $request, string $job, UpdateCronJob $update): JsonResponse
    {
        $item = CronJob::query()->whereKey($job)->where('team_id', $this->teamId($request))->firstOrFail();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'], 'command' => ['sometimes', 'string', 'max:4096'],
            'schedule' => ['sometimes', 'string', 'max:100'], 'active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => self::cronJobResource($update->execute($item, $data))]);
    }

    public function deleteCronJob(Request $request, string $job, DeleteCronJob $delete): JsonResponse
    {
        $item = CronJob::query()->whereKey($job)->where('team_id', $this->teamId($request))->firstOrFail();
        $delete->execute($item);

        return response()->json(status: 204);
    }

    public function cronExecutions(Request $request, string $job): JsonResponse
    {
        $item = CronJob::query()->whereKey($job)->where('team_id', $this->teamId($request))->firstOrFail();
        $executions = $item->executions()->latest('started_at')->limit(100)->get();

        return response()->json(['data' => $executions->map(static fn (CronExecution $execution): array => self::cronExecutionResource($execution))]);
    }

    public function recordCronExecution(Request $request, string $job, RecordCronExecution $record): JsonResponse
    {
        $item = CronJob::query()->whereKey($job)->where('team_id', $this->teamId($request))->firstOrFail();
        $data = $request->validate([
            'started_at' => ['sometimes', 'date'], 'finished_at' => ['nullable', 'date'],
            'exit_code' => ['nullable', 'integer'], 'output' => ['nullable', 'string', 'max:65535'],
            'error_output' => ['nullable', 'string', 'max:65535'], 'duration' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json(['data' => self::cronExecutionResource($record->execute($item, $data))], 201);
    }

    public function store(Request $request, CreateDomain $create): JsonResponse
    {
        $teamId = $request->user()?->current_team_id;
        abort_if($teamId === null, 403, 'A current team is required.');
        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:253'],
            'account_id' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $domain = $create->execute(array_merge($data, ['team_id' => $teamId]));

        return response()->json(['data' => self::resource($domain)], 201);
    }

    public function update(Request $request, Domain $domain, UpdateDomain $update): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate([
            'hostname' => ['sometimes', 'string', 'max:253'],
            'account_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        return response()->json(['data' => self::resource($update->execute($domain, $data))]);
    }

    public function activate(Request $request, Domain $domain, ActivateDomain $activate): JsonResponse
    {
        $this->assertTeam($request, $domain);

        return response()->json(['data' => self::resource($activate->execute($domain))]);
    }

    public function suspend(Request $request, Domain $domain, SuspendDomain $suspend): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return response()->json(['data' => self::resource($suspend->execute($domain, $data['reason']))]);
    }

    public function archive(Request $request, Domain $domain, ArchiveDomain $archive): JsonResponse
    {
        $this->assertTeam($request, $domain);

        return response()->json(['data' => self::resource($archive->execute($domain))]);
    }

    public function virtualHost(Request $request, Domain $domain, CreateVirtualHost $create): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate(['node_id' => ['required', 'uuid'], 'server' => ['required', 'in:nginx,apache'], 'runtime' => ['required', 'string', 'max:80'], 'document_root' => ['required', 'string', 'max:1024'], 'desired_state' => ['nullable', 'array']]);
        $host = $create->execute($domain, $data);

        return response()->json(['data' => ['id' => $host->getKey(), 'type' => 'control-panel-virtual-host', 'attributes' => $host->only(['domain_id', 'node_id', 'server', 'runtime', 'document_root', 'desired_state', 'active'])]], 201);
    }

    public function updateVirtualHost(Request $request, string $id, UpdateVirtualHost $update): JsonResponse
    {
        $teamId = $this->teamId($request);
        $host = VirtualHost::query()->whereKey($id)->whereHas('domain', fn (Builder $query) => $query->where('team_id', $teamId))->with('domain')->firstOrFail();
        $data = $request->validate([
            'domain_id' => ['sometimes', 'uuid'],
            'node_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'server' => ['sometimes', 'in:nginx,apache'],
            'runtime' => ['sometimes', 'nullable', 'string', 'max:80'],
            'document_root' => ['sometimes', 'string', 'starts_with:/', 'max:2048'],
            'desired_state' => ['sometimes', 'nullable', 'array'],
            'active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => ['id' => $id, 'type' => 'control-panel-virtual-host', 'attributes' => $update->execute($host, $data)->only(['domain_id', 'node_id', 'server', 'runtime', 'document_root', 'desired_state', 'active'])]]);
    }

    public function deleteVirtualHost(Request $request, string $id, DeleteVirtualHost $delete): JsonResponse
    {
        $teamId = $this->teamId($request);
        $host = VirtualHost::query()->whereKey($id)->whereHas('domain', fn (Builder $query) => $query->where('team_id', $teamId))->firstOrFail();
        $delete->execute($host);

        return response()->json(status: 204);
    }

    public function redirect(Request $request, Domain $domain, CreateRedirect $create): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate(['source' => ['required', 'string', 'max:1024'], 'destination' => ['required', 'string', 'max:2048'], 'status_code' => ['nullable', 'integer', 'in:301,302,307,308'], 'active' => ['sometimes', 'boolean'], 'match_query_string' => ['sometimes', 'boolean'], 'is_regex' => ['sometimes', 'boolean'], 'priority' => ['sometimes', 'integer', 'min:0', 'max:100000']]);
        $redirect = $create->execute($domain, $data);

        return response()->json(['data' => self::redirectResource($redirect)], 201);
    }

    public function updateRedirect(Request $request, string $id, UpdateRedirect $update): JsonResponse
    {
        $redirect = Redirect::query()->whereKey($id)->where('team_id', $this->teamId($request))->firstOrFail();
        $data = $request->validate(['source' => ['sometimes', 'string', 'max:1024'], 'destination' => ['sometimes', 'string', 'max:2048'], 'status_code' => ['sometimes', 'integer', 'in:301,302,307,308'], 'active' => ['sometimes', 'boolean'], 'match_query_string' => ['sometimes', 'boolean'], 'is_regex' => ['sometimes', 'boolean'], 'priority' => ['sometimes', 'integer', 'min:0', 'max:100000']]);

        return response()->json(['data' => self::redirectResource($update->execute($redirect, $data))]);
    }

    public function deleteRedirect(Request $request, string $id, DeleteRedirect $delete): JsonResponse
    {
        $redirect = Redirect::query()->whereKey($id)->where('team_id', $this->teamId($request))->firstOrFail();
        $delete->execute($redirect);

        return response()->json(status: 204);
    }

    public function mimeType(Request $request, Domain $domain, CreateMimeType $create): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate(['extension' => ['required', 'string', 'max:32'], 'mime_type' => ['required', 'string', 'max:255'], 'active' => ['sometimes', 'boolean']]);
        $mimeType = $create->execute($domain, $data);

        return response()->json(['data' => self::mimeTypeResource($mimeType)], 201);
    }

    public function certificate(Request $request, Domain $domain, RequestCertificate $requestCertificate): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate(['issuer' => ['nullable', 'string', 'max:120'], 'auto_renew' => ['sometimes', 'boolean'], 'metadata' => ['nullable', 'array']]);
        $certificate = $requestCertificate->execute($domain, $data);

        return response()->json(['data' => ['id' => $certificate->getKey(), 'type' => 'control-panel-ssl-certificate', 'attributes' => $certificate->only(['domain_id', 'issuer', 'status', 'auto_renew', 'expires_at'])]], 202);
    }

    public function resourceRecord(Request $request, RegisterHostingResource $register): JsonResponse
    {
        $teamId = $request->user()?->current_team_id;
        abort_if($teamId === null, 403, 'A current team is required.');
        $data = $request->validate(['kind' => ['required', 'in:runtime,server,log,application'], 'payload' => ['required', 'array']]);
        $item = $register->execute(array_merge($data['payload'], ['kind' => $data['kind'], 'team_id' => $teamId]));

        return response()->json(['data' => ['id' => $item->getKey(), 'type' => 'control-panel-web-hosting-'.$data['kind'], 'attributes' => self::resourceAttributes($item, $data['kind'])]], 201);
    }

    public function resources(Request $request, string $kind): JsonResponse
    {
        $teamId = $request->user()?->current_team_id;
        abort_if($teamId === null, 403, 'A current team is required.');
        $models = [
            'runtime' => RuntimeVersion::class, 'server' => WebServer::class, 'log' => HostingLog::class,
            'application' => HostedApplication::class, 'redirect' => Redirect::class, 'certificate' => SslCertificate::class,
            'virtual-host' => VirtualHost::class, 'mime-type' => MimeType::class,
        ];
        abort_unless(isset($models[$kind]), 404, 'Unsupported hosting resource.');
        $query = $models[$kind]::query();
        if ($kind === 'virtual-host') {
            $query->whereHas('domain', fn (Builder $domain) => $domain->where('team_id', $teamId));
        } else {
            $query->where('team_id', $teamId);
        }
        $page = $query->latest()->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json(['data' => $page->through(fn (Model $model): array => ['id' => $model->getKey(), 'type' => 'control-panel-web-hosting-'.$kind, 'attributes' => self::resourceAttributes($model, $kind)]), 'meta' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function applications(Request $request): JsonResponse
    {
        $teamId = $this->teamId($request);
        $page = HostedApplication::query()->where('team_id', $teamId)->with('domain')->latest()->paginate($this->perPage($request));

        return response()->json(['data' => $page->through(fn (HostedApplication $application): array => self::applicationResource($application)), 'meta' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function applicationStatistics(Request $request, ApplicationStatistics $statistics): JsonResponse
    {
        $teamId = $this->teamId($request);
        $days = min(max($request->integer('days', 30), 1), 365);

        return response()->json(['data' => [
            'type' => 'control-panel-hosted-application-statistics',
            'attributes' => $statistics->execute($teamId, $days),
        ]]);
    }

    public function application(Request $request, RegisterHostingResource $register): JsonResponse
    {
        $teamId = $this->teamId($request);
        $data = $request->validate([
            'domain_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:wordpress,laravel,static,nodejs,custom'], 'version' => ['nullable', 'string', 'max:80'],
            'document_root' => ['required', 'string', 'starts_with:/', 'max:2048'], 'config' => ['nullable', 'array'],
        ]);
        $domain = Domain::query()->where('team_id', $teamId)->findOrFail($data['domain_id']);
        $application = $register->execute([...$data, 'kind' => 'application', 'team_id' => $teamId, 'domain_id' => $domain->getKey()]);

        return response()->json(['data' => self::applicationResource($application)], 201);
    }

    public function updateApplication(Request $request, string $id, UpdateHostedApplication $update): JsonResponse
    {
        $teamId = $this->teamId($request);
        $application = HostedApplication::query()->whereKey($id)->where('team_id', $teamId)->firstOrFail();
        $data = $request->validate([
            'domain_id' => ['sometimes', 'uuid'],
            'name' => ['sometimes', 'string', 'max:160'],
            'type' => ['sometimes', 'in:wordpress,laravel,static,nodejs,custom'],
            'version' => ['sometimes', 'nullable', 'string', 'max:80'],
            'document_root' => ['sometimes', 'string', 'starts_with:/', 'max:2048'],
            'config' => ['sometimes', 'nullable', 'array'],
        ]);

        return response()->json(['data' => self::applicationResource($update->execute($application, $data))]);
    }

    public function deleteApplication(Request $request, string $id, DeleteHostedApplication $delete): JsonResponse
    {
        $teamId = $this->teamId($request);
        $application = HostedApplication::query()->whereKey($id)->where('team_id', $teamId)->firstOrFail();
        $delete->execute($application);

        return response()->json(status: 204);
    }

    public function applicationPerformance(Request $request, HostedApplication $application): JsonResponse
    {
        $this->assertApplicationTeam($request, $application);
        $hours = min(max($request->integer('hours', 24), 1), 720);
        $metrics = $application->performanceMetrics()->where('checked_at', '>=', now()->subHours($hours))->oldest('checked_at')->get();
        $total = $metrics->count();

        return response()->json(['data' => $metrics, 'meta' => ['hours' => $hours, 'total_checks' => $total, 'uptime_percentage' => $total === 0 ? null : round(($metrics->where('healthy', true)->count() / $total) * 100, 2), 'average_response_time' => $total === 0 ? null : round((float) $metrics->avg('response_time_ms'), 2)]]);
    }

    public function applicationHealth(Request $request, HostedApplication $application, CheckApplicationHealth $check): JsonResponse
    {
        $this->assertApplicationTeam($request, $application);

        return response()->json(['data' => $check->execute($application)], 201);
    }

    public function wordpressUpdate(Request $request, HostedApplication $application, CheckWordPressUpdates $check): JsonResponse
    {
        $this->assertApplicationTeam($request, $application);

        return response()->json(['data' => $check->execute($application)]);
    }

    public function wordpressClone(Request $request, HostedApplication $application, CreateWordPressOperation $create): JsonResponse
    {
        return $this->createWordPressOperation($request, $application, WordPressOperationType::Clone, $create);
    }

    public function wordpressUpdateOperation(Request $request, HostedApplication $application, CreateWordPressOperation $create): JsonResponse
    {
        return $this->createWordPressOperation($request, $application, WordPressOperationType::Update, $create);
    }

    public function wordpressRollback(Request $request, HostedApplication $application, CreateWordPressOperation $create): JsonResponse
    {
        return $this->createWordPressOperation($request, $application, WordPressOperationType::Rollback, $create);
    }

    public function runWordPressOperation(Request $request, string $operation, RunWordPressOperation $run): JsonResponse
    {
        $item = WordPressOperation::query()->whereKey($operation)->where('team_id', $this->teamId($request))->firstOrFail();

        return response()->json(['data' => self::wordpressOperationResource($run->execute($item))]);
    }

    public function showWordPressOperation(Request $request, string $operation): JsonResponse
    {
        $item = WordPressOperation::query()->whereKey($operation)->where('team_id', $this->teamId($request))->firstOrFail();

        return response()->json(['data' => self::wordpressOperationResource($item)]);
    }

    public function deployments(Request $request, ListGitDeployments $list): JsonResponse
    {
        $teamId = $request->user()?->current_team_id;
        abort_if($teamId === null, 403, 'A current team is required.');
        $deployments = $list->execute($teamId, $request->integer('per_page', 25));

        return response()->json(['data' => $deployments->through(static fn (GitDeployment $deployment): array => self::deploymentResource($deployment)), 'meta' => ['current_page' => $deployments->currentPage(), 'per_page' => $deployments->perPage(), 'total' => $deployments->total()]]);
    }

    public function deployment(Request $request, Domain $domain, RegisterGitDeployment $register): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate([
            'repository_url' => ['required', 'string', 'max:2048'], 'branch' => ['nullable', 'string', 'max:255'],
            'deploy_path' => ['required', 'string', 'starts_with:/', 'max:1024'], 'deploy_key' => ['nullable', 'string'],
            'use_oauth' => ['sometimes', 'boolean'], 'connected_account_id' => ['nullable', 'string', 'max:255'],
            'container_id' => ['nullable', 'string', 'max:255'], 'kubernetes_pod_name' => ['nullable', 'string', 'max:255'],
            'kubernetes_namespace' => ['nullable', 'string', 'max:255'], 'build_command' => ['nullable', 'string', 'max:1024'],
            'deploy_command' => ['nullable', 'string', 'max:1024'], 'auto_deploy' => ['sometimes', 'boolean'],
        ]);
        $deployment = $register->execute($domain, $data);

        return response()->json(['data' => self::deploymentResource($deployment)], 201);
    }

    public function launch(Request $request, Domain $domain, CreateWebsiteLaunch $create): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate([
            'node_id' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
        ]);

        return response()->json(['data' => self::launchResource($create->execute($domain, array_merge($data, ['team_id' => $this->teamId($request)])))], 202);
    }

    public function runLaunch(Request $request, string $launch, RunWebsiteLaunch $run): JsonResponse
    {
        $item = WebsiteLaunch::query()->whereKey($launch)->where('team_id', $this->teamId($request))->firstOrFail();

        return response()->json(['data' => self::launchResource($run->execute($item))]);
    }

    public function showLaunch(Request $request, string $launch): JsonResponse
    {
        $item = WebsiteLaunch::query()->whereKey($launch)->where('team_id', $this->teamId($request))->firstOrFail();

        return response()->json(['data' => self::launchResource($item)]);
    }

    public function deploy(Request $request, string $deployment, RequestGitDeployment $requestDeployment): JsonResponse
    {
        $teamId = $this->teamId($request);
        $item = GitDeployment::query()->whereKey($deployment)->where('team_id', $teamId)->firstOrFail();

        return response()->json(['data' => self::deploymentResource($requestDeployment->execute($item))], 202);
    }

    public function githubWebhook(Request $request, string $deployment, RequestGitDeployment $requestDeployment): JsonResponse
    {
        $item = GitDeployment::query()->whereKey($deployment)->where('repository_type', 'github')->firstOrFail();
        $signature = (string) $request->header('X-Hub-Signature-256');

        abort_unless($signature !== '' && GitDeployment::validateGitHubWebhook($request->getContent(), $signature, (string) $item->webhook_secret), 401, 'Invalid webhook signature.');

        return $this->triggerWebhook($request, $item, $requestDeployment);
    }

    public function gitlabWebhook(Request $request, string $deployment, RequestGitDeployment $requestDeployment): JsonResponse
    {
        $item = GitDeployment::query()->whereKey($deployment)->where('repository_type', 'gitlab')->firstOrFail();
        $token = (string) $request->header('X-Gitlab-Token');

        abort_unless($token !== '' && GitDeployment::validateGitLabWebhook($token, (string) $item->webhook_secret), 401, 'Invalid webhook token.');

        return $this->triggerWebhook($request, $item, $requestDeployment);
    }

    public function genericWebhook(Request $request, string $deployment, RequestGitDeployment $requestDeployment): JsonResponse
    {
        $item = GitDeployment::query()->whereKey($deployment)->firstOrFail();
        $secret = (string) ($request->header('X-Webhook-Secret') ?: $request->query('secret', ''));

        abort_unless($secret !== '' && hash_equals((string) $item->webhook_secret, $secret), 401, 'Invalid webhook secret.');

        return $this->triggerWebhook($request, $item, $requestDeployment);
    }

    private function triggerWebhook(Request $request, GitDeployment $deployment, RequestGitDeployment $requestDeployment): JsonResponse
    {
        $payload = $request->json()->all();
        $branch = str_replace('refs/heads/', '', (string) ($payload['ref'] ?? ''));

        if (! $deployment->auto_deploy || $branch !== $deployment->branch) {
            return response()->json(['message' => 'Deployment not triggered.']);
        }

        return response()->json(['data' => self::deploymentResource($requestDeployment->execute($deployment))], 202);
    }

    public function phpConfiguration(Request $request, Domain $domain, SavePhpConfiguration $save): JsonResponse
    {
        $this->assertTeam($request, $domain);
        $data = $request->validate([
            'php_version' => ['required', 'string', 'in:7.4,8.0,8.1,8.2,8.3,8.4,8.5'],
            'memory_limit' => ['nullable', 'integer', 'min:1', 'max:1048576'], 'upload_max_filesize' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'post_max_size' => ['nullable', 'integer', 'min:1', 'max:1048576'], 'max_execution_time' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'max_input_time' => ['nullable', 'integer', 'min:1', 'max:86400'], 'max_input_vars' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'display_errors' => ['sometimes', 'boolean'], 'short_open_tag' => ['sometimes', 'boolean'],
            'error_reporting' => ['nullable', 'string', 'max:255'], 'session_save_path' => ['nullable', 'string', 'max:1024'],
            'custom_settings' => ['nullable', 'array'],
        ]);
        $configuration = $save->execute($domain, $data);

        return response()->json(['data' => self::phpConfigurationResource($configuration)]);
    }

    private static function resource(Domain $domain): array
    {
        return ['id' => $domain->getKey(), 'type' => 'control-panel-domain', 'attributes' => $domain->only(['hostname', 'status', 'account_id', 'metadata'])];
    }

    private static function resourceAttributes(Model $model, string $kind): array
    {
        return $model->only(self::RESOURCE_FIELDS[$kind]);
    }

    /** @return array<string, mixed> */
    private static function usageResource(ResourceUsage $usage): array
    {
        return ['id' => $usage->getKey(), 'type' => 'control-panel-resource-usage', 'attributes' => $usage->only(['domain_id', 'month', 'year', 'disk_usage_mb', 'bandwidth_usage_mb'])];
    }

    private static function cronJobResource(CronJob $job): array
    {
        return ['id' => $job->getKey(), 'type' => 'control-panel-cron-job', 'attributes' => $job->only(['team_id', 'domain_id', 'name', 'command', 'schedule', 'active', 'last_run_at', 'next_run_at', 'output', 'error_output'])];
    }

    private static function cronExecutionResource(CronExecution $execution): array
    {
        return ['id' => $execution->getKey(), 'type' => 'control-panel-cron-execution', 'attributes' => $execution->only(['cron_job_id', 'started_at', 'finished_at', 'exit_code', 'output', 'error_output', 'duration'])];
    }

    /** @return array<string, mixed> */
    private static function redirectResource(Redirect $redirect): array
    {
        return ['id' => $redirect->getKey(), 'type' => 'control-panel-redirect', 'attributes' => $redirect->only(['domain_id', 'source', 'destination', 'status_code', 'active', 'source_path', 'destination_url', 'redirect_type', 'match_query_string', 'is_regex', 'priority'])];
    }

    /** @return array<string, mixed> */
    private static function mimeTypeResource(MimeType $mimeType): array
    {
        return ['id' => $mimeType->getKey(), 'type' => 'control-panel-mime-type', 'attributes' => $mimeType->only(['domain_id', 'extension', 'mime_type', 'active'])];
    }

    /** @return array<string, mixed> */
    private static function subdomainResource(Subdomain $subdomain): array
    {
        return ['id' => $subdomain->getKey(), 'type' => 'control-panel-subdomain', 'attributes' => $subdomain->only(['domain_id', 'subdomain', 'document_root', 'php_version', 'active', 'redirect_url', 'redirect_type']) + ['full_name' => $subdomain->full_name]];
    }

    private function assertTeam(Request $request, Domain $domain): void
    {
        abort_if($request->user()?->current_team_id === null, 403, 'A current team is required.');
        abort_unless((string) $domain->team_id === (string) $request->user()?->current_team_id, 404);
    }

    private function assertApplicationTeam(Request $request, HostedApplication $application): void
    {
        abort_if($request->user()?->current_team_id === null, 403, 'A current team is required.');
        abort_unless((string) $application->team_id === (string) $request->user()?->current_team_id, 404);
    }

    private function teamId(Request $request): string
    {
        $teamId = $request->user()?->current_team_id;
        abort_if($teamId === null, 403, 'A current team is required.');

        return (string) $teamId;
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 25), 1), 100);
    }

    /** @return array<string, mixed> */
    private static function applicationResource(HostedApplication $application): array
    {
        return ['id' => $application->getKey(), 'type' => 'control-panel-hosted-application', 'attributes' => $application->only(['domain_id', 'name', 'type', 'version', 'document_root', 'status', 'config']) + ['health_status' => $application->healthStatus()]];
    }

    /** @return array<string, mixed> */
    private static function deploymentResource(GitDeployment $deployment): array
    {
        return ['id' => $deployment->getKey(), 'type' => 'control-panel-git-deployment', 'attributes' => $deployment->only(['domain_id', 'repository_url', 'repository_type', 'branch', 'deploy_path', 'use_oauth', 'status', 'auto_deploy', 'last_deployed_at', 'last_commit_hash'])];
    }

    /** @return array<string, mixed> */
    private static function launchResource(WebsiteLaunch $launch): array
    {
        return ['id' => $launch->getKey(), 'type' => 'control-panel-website-launch', 'attributes' => $launch->only(['domain_id', 'node_id', 'idempotency_key', 'status', 'current_stage', 'config', 'result', 'steps', 'error', 'started_at', 'finished_at'])];
    }

    /** @return array<string, mixed> */
    private static function wordpressOperationResource(WordPressOperation $operation): array
    {
        return ['id' => $operation->getKey(), 'type' => 'control-panel-wordpress-operation', 'attributes' => $operation->only(['application_id', 'target_application_id', 'operation', 'idempotency_key', 'status', 'result', 'error', 'started_at', 'finished_at'])];
    }

    private function createWordPressOperation(Request $request, HostedApplication $application, WordPressOperationType $type, CreateWordPressOperation $create): JsonResponse
    {
        $this->assertApplicationTeam($request, $application);
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:255'],
            'target_domain_id' => ['nullable', 'uuid'],
            'target_application_id' => ['nullable', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
            'document_root' => ['nullable', 'string', 'starts_with:/', 'max:2048'],
            'config' => ['sometimes', 'array'],
        ]);

        return response()->json(['data' => self::wordpressOperationResource($create->execute($application, $type, array_merge($data, ['team_id' => $this->teamId($request)])))], 202);
    }

    /** @return array<string, mixed> */
    private static function phpConfigurationResource(PhpConfiguration $configuration): array
    {
        return ['id' => $configuration->getKey(), 'type' => 'control-panel-php-configuration', 'attributes' => $configuration->only(['domain_id', 'php_version', 'memory_limit', 'upload_max_filesize', 'post_max_size', 'max_execution_time', 'max_input_time', 'max_input_vars', 'display_errors', 'short_open_tag', 'error_reporting', 'session_save_path', 'custom_settings'])];
    }
}
