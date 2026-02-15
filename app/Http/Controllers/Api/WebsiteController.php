<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\WebsiteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class WebsiteController extends Controller
{
    protected WebsiteService $service;

    public function __construct(WebsiteService $service)
    {
        $this->service = $service;
    }

    /**
     * List all websites for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $websites = Website::where('user_id', $request->user()->id)
            ->with(['server'])
            ->paginate($request->get('per_page', 15));

        return response()->json($websites);
    }

    /**
     * Get a specific website
     */
    public function show(Request $request, Website $website): JsonResponse
    {
        if ($website->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $website->load(['server', 'performanceMetrics' => function($query) {
            $query->orderBy('checked_at', 'desc')->limit(10);
        }]);

        return response()->json($website);
    }

    /**
     * Create a new website
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:websites,domain',
            'description' => 'nullable|string|max:500',
            'platform' => 'nullable|string|in:wordpress,laravel,static,nodejs,custom',
            'php_version' => 'nullable|string|in:8.1,8.2,8.3,8.4',
            'database_type' => 'nullable|string|in:mysql,mariadb,postgresql,sqlite,none',
            'document_root' => 'nullable|string|max:255',
            'server_id' => 'nullable|exists:servers,id',
            'ssl_enabled' => 'nullable|boolean',
            'auto_ssl' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;

        $result = $this->service->create($data);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
                'website' => $result['website'],
            ], 201);
        }

        return response()->json([
            'error' => $result['message']
        ], 500);
    }

    /**
     * Update a website
     */
    public function update(Request $request, Website $website): JsonResponse
    {
        if ($website->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'domain' => 'sometimes|string|max:255|unique:websites,domain,' . $website->id,
            'description' => 'nullable|string|max:500',
            'platform' => 'nullable|string|in:wordpress,laravel,static,nodejs,custom',
            'php_version' => 'nullable|string|in:8.1,8.2,8.3,8.4',
            'database_type' => 'nullable|string|in:mysql,mariadb,postgresql,sqlite,none',
            'document_root' => 'nullable|string|max:255',
            'server_id' => 'nullable|exists:servers,id',
            'ssl_enabled' => 'nullable|boolean',
            'auto_ssl' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,inactive,pending,maintenance,error',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->service->update($website, $validator->validated());

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
                'website' => $result['website'],
            ]);
        }

        return response()->json([
            'error' => $result['message']
        ], 500);
    }

    /**
     * Delete a website
     */
    public function destroy(Request $request, Website $website): JsonResponse
    {
        if ($website->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $result = $this->service->delete($website);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message']
            ]);
        }

        return response()->json([
            'error' => $result['message']
        ], 500);
    }

    /**
     * Get performance metrics for a website
     */
    public function performance(Request $request, Website $website): JsonResponse
    {
        if ($website->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $hours = $request->get('hours', 24);
        
        $metrics = $website->performanceMetrics()
            ->where('checked_at', '>=', now()->subHours($hours))
            ->orderBy('checked_at', 'asc')
            ->get();

        return response()->json([
            'website' => $website->only(['id', 'name', 'domain']),
            'metrics' => $metrics,
            'summary' => [
                'uptime_percentage' => $website->uptime_percentage,
                'average_response_time' => $website->average_response_time,
                'total_checks' => $metrics->count(),
                'successful_checks' => $metrics->where('uptime_status', true)->count(),
                'failed_checks' => $metrics->where('uptime_status', false)->count(),
            ]
        ]);
    }

    /**
     * Get statistics for all websites
     */
    public function statistics(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        
        $stats = [
            'total_websites' => Website::where('user_id', $userId)->count(),
            'active_websites' => Website::where('user_id', $userId)
                ->where('status', Website::STATUS_ACTIVE)
                ->count(),
            'total_visitors' => Website::where('user_id', $userId)
                ->sum('monthly_visitors'),
            'total_bandwidth' => Website::where('user_id', $userId)
                ->sum('monthly_bandwidth'),
            'average_uptime' => Website::where('user_id', $userId)
                ->where('status', Website::STATUS_ACTIVE)
                ->avg('uptime_percentage'),
            'websites_by_platform' => Website::where('user_id', $userId)
                ->selectRaw('platform, COUNT(*) as count')
                ->groupBy('platform')
                ->get(),
            'websites_by_status' => Website::where('user_id', $userId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get(),
        ];

        return response()->json($stats);
    }
}
