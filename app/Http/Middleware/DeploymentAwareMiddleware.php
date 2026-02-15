<?php

namespace App\Http\Middleware;

use App\Services\DeploymentDetectionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class DeploymentAwareMiddleware
{
    protected DeploymentDetectionService $detectionService;

    public function __construct(DeploymentDetectionService $detectionService)
    {
        $this->detectionService = $detectionService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get deployment information
        $deploymentInfo = $this->detectionService->getDeploymentInfo();

        // Share with all views
        View::share('deploymentInfo', $deploymentInfo);

        // Add to request
        $request->merge(['_deployment_info' => $deploymentInfo]);

        return $next($request);
    }
}
