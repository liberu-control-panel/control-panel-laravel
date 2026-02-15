<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VirtualHost;
use App\Services\VirtualHostService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class VirtualHostController extends Controller
{
    protected VirtualHostService $service;

    public function __construct(VirtualHostService $service)
    {
        $this->service = $service;
    }

    /**
     * List all virtual hosts for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $virtualHosts = VirtualHost::where('user_id', $request->user()->id)
            ->with(['domain', 'server', 'sslCertificate'])
            ->paginate($request->get('per_page', 15));

        return response()->json($virtualHosts);
    }

    /**
     * Get a specific virtual host
     */
    public function show(Request $request, VirtualHost $virtualHost): JsonResponse
    {
        if ($virtualHost->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $virtualHost->load(['domain', 'server', 'sslCertificate']);

        return response()->json($virtualHost);
    }

    /**
     * Create a new virtual host
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hostname' => 'required|string|max:255|unique:virtual_hosts,hostname',
            'domain_id' => 'nullable|exists:domains,id',
            'server_id' => 'nullable|exists:servers,id',
            'document_root' => 'nullable|string|max:255',
            'php_version' => 'nullable|string|in:8.1,8.2,8.3,8.4,8.5',
            'ssl_enabled' => 'nullable|boolean',
            'letsencrypt_enabled' => 'nullable|boolean',
            'port' => 'nullable|integer|min:1|max:65535',
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
                'virtual_host' => $result['virtual_host'],
            ], 201);
        }

        return response()->json([
            'error' => $result['message']
        ], 500);
    }

    /**
     * Update a virtual host
     */
    public function update(Request $request, VirtualHost $virtualHost): JsonResponse
    {
        if ($virtualHost->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'hostname' => 'sometimes|string|max:255|unique:virtual_hosts,hostname,' . $virtualHost->id,
            'domain_id' => 'nullable|exists:domains,id',
            'server_id' => 'nullable|exists:servers,id',
            'document_root' => 'nullable|string|max:255',
            'php_version' => 'nullable|string|in:8.1,8.2,8.3,8.4,8.5',
            'ssl_enabled' => 'nullable|boolean',
            'letsencrypt_enabled' => 'nullable|boolean',
            'port' => 'nullable|integer|min:1|max:65535',
            'status' => 'nullable|string|in:active,inactive,pending,error',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->service->update($virtualHost, $validator->validated());

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
                'virtual_host' => $result['virtual_host'],
            ]);
        }

        return response()->json([
            'error' => $result['message']
        ], 500);
    }

    /**
     * Delete a virtual host
     */
    public function destroy(Request $request, VirtualHost $virtualHost): JsonResponse
    {
        if ($virtualHost->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $result = $this->service->delete($virtualHost);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message']
            ]);
        }

        return response()->json([
            'error' => $result['message']
        ], 500);
    }
}
