<?php

namespace App\Http\Controllers;

use App\Models\GitDeployment;
use App\Services\GitDeploymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected GitDeploymentService $deploymentService;

    public function __construct(GitDeploymentService $deploymentService)
    {
        $this->deploymentService = $deploymentService;
    }

    /**
     * Handle GitHub webhook
     */
    public function github(Request $request, GitDeployment $deployment): JsonResponse
    {
        try {
            // Validate webhook signature
            $signature = $request->header('X-Hub-Signature-256');
            $payload = $request->getContent();

            if (!$signature || !$this->deploymentService->validateGitHubWebhook($payload, $signature, $deployment->webhook_secret)) {
                Log::warning("Invalid GitHub webhook signature for deployment {$deployment->id}");
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Parse payload
            $data = $request->json()->all();

            // Handle webhook
            if ($this->deploymentService->handleWebhook($deployment, $data)) {
                return response()->json(['message' => 'Deployment triggered successfully']);
            }

            return response()->json(['message' => 'Deployment not triggered'], 200);

        } catch (\Exception $e) {
            Log::error("GitHub webhook error: " . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle GitLab webhook
     */
    public function gitlab(Request $request, GitDeployment $deployment): JsonResponse
    {
        try {
            // Validate webhook token
            $token = $request->header('X-Gitlab-Token');

            if (!$token || !$this->deploymentService->validateGitLabWebhook($token, $deployment->webhook_secret)) {
                Log::warning("Invalid GitLab webhook token for deployment {$deployment->id}");
                return response()->json(['error' => 'Invalid token'], 401);
            }

            // Parse payload
            $data = $request->json()->all();

            // Handle webhook
            if ($this->deploymentService->handleWebhook($deployment, $data)) {
                return response()->json(['message' => 'Deployment triggered successfully']);
            }

            return response()->json(['message' => 'Deployment not triggered'], 200);

        } catch (\Exception $e) {
            Log::error("GitLab webhook error: " . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle generic webhook (Bitbucket, etc.)
     */
    public function generic(Request $request, GitDeployment $deployment): JsonResponse
    {
        try {
            // Validate webhook secret via query parameter or header
            $secret = $request->query('secret') ?? $request->header('X-Webhook-Secret');

            if (!$secret || !hash_equals($deployment->webhook_secret, $secret)) {
                Log::warning("Invalid webhook secret for deployment {$deployment->id}");
                return response()->json(['error' => 'Invalid secret'], 401);
            }

            // Parse payload
            $data = $request->json()->all();

            // Handle webhook
            if ($this->deploymentService->handleWebhook($deployment, $data)) {
                return response()->json(['message' => 'Deployment triggered successfully']);
            }

            return response()->json(['message' => 'Deployment not triggered'], 200);

        } catch (\Exception $e) {
            Log::error("Generic webhook error: " . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
