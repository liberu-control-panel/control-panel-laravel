<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StandaloneServiceChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceStatusController extends Controller
{
    protected StandaloneServiceChecker $serviceChecker;

    public function __construct(StandaloneServiceChecker $serviceChecker)
    {
        $this->serviceChecker = $serviceChecker;
    }

    /**
     * Check all service statuses
     */
    public function checkAll(Request $request): JsonResponse
    {
        $status = $this->serviceChecker->checkAllServices();

        return response()->json($status);
    }

    /**
     * Get missing services
     */
    public function missing(Request $request): JsonResponse
    {
        $missing = $this->serviceChecker->getMissingServices();

        return response()->json([
            'missing_services' => $missing,
            'count' => count($missing),
        ]);
    }

    /**
     * Get stopped services
     */
    public function stopped(Request $request): JsonResponse
    {
        $stopped = $this->serviceChecker->getStoppedServices();

        return response()->json([
            'stopped_services' => $stopped,
            'count' => count($stopped),
        ]);
    }

    /**
     * Get installation commands for missing services
     */
    public function installCommands(Request $request): JsonResponse
    {
        $commands = $this->serviceChecker->getInstallationCommands();

        return response()->json([
            'commands' => $commands,
            'count' => count($commands),
        ]);
    }

    /**
     * Check specific service
     */
    public function checkService(Request $request, string $service): JsonResponse
    {
        $method = 'check' . ucfirst($service);
        
        if (!method_exists($this->serviceChecker, $method)) {
            return response()->json([
                'error' => 'Service not found'
            ], 404);
        }

        $status = $this->serviceChecker->$method();

        return response()->json($status);
    }
}
