<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DnsSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class DnsController extends Controller
{
    /**
     * List all DNS settings for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $dnsSettings = DnsSetting::whereHas('domain', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with('domain')
            ->paginate($request->get('per_page', 15));

        return response()->json($dnsSettings);
    }

    /**
     * Get DNS settings for a specific domain
     */
    public function show(Request $request, DnsSetting $dnsSetting): JsonResponse
    {
        if ($dnsSetting->domain->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $dnsSetting->load('domain');

        return response()->json($dnsSetting);
    }

    /**
     * Create a new DNS record
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|exists:domains,id',
            'record_type' => 'required|string|in:A,AAAA,CNAME,MX,TXT,NS,SRV,CAA',
            'name' => 'required|string|max:255',
            'value' => 'required|string|max:1000',
            'ttl' => 'nullable|integer|min:60|max:86400',
            'priority' => 'nullable|integer|min:0|max:65535',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        
        // Verify domain ownership
        $domain = \App\Models\Domain::findOrFail($data['domain_id']);
        if ($domain->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $dnsSetting = DnsSetting::create($data);

        return response()->json([
            'message' => 'DNS record created successfully',
            'dns_setting' => $dnsSetting->load('domain'),
        ], 201);
    }

    /**
     * Update a DNS record
     */
    public function update(Request $request, DnsSetting $dnsSetting): JsonResponse
    {
        if ($dnsSetting->domain->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'record_type' => 'sometimes|string|in:A,AAAA,CNAME,MX,TXT,NS,SRV,CAA',
            'name' => 'sometimes|string|max:255',
            'value' => 'sometimes|string|max:1000',
            'ttl' => 'nullable|integer|min:60|max:86400',
            'priority' => 'nullable|integer|min:0|max:65535',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dnsSetting->update($validator->validated());

        return response()->json([
            'message' => 'DNS record updated successfully',
            'dns_setting' => $dnsSetting->fresh(['domain']),
        ]);
    }

    /**
     * Delete a DNS record
     */
    public function destroy(Request $request, DnsSetting $dnsSetting): JsonResponse
    {
        if ($dnsSetting->domain->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $dnsSetting->delete();

        return response()->json([
            'message' => 'DNS record deleted successfully'
        ]);
    }

    /**
     * Bulk create DNS records (useful for zone imports)
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|exists:domains,id',
            'records' => 'required|array|min:1',
            'records.*.record_type' => 'required|string|in:A,AAAA,CNAME,MX,TXT,NS,SRV,CAA',
            'records.*.name' => 'required|string|max:255',
            'records.*.value' => 'required|string|max:1000',
            'records.*.ttl' => 'nullable|integer|min:60|max:86400',
            'records.*.priority' => 'nullable|integer|min:0|max:65535',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify domain ownership
        $domain = \App\Models\Domain::findOrFail($request->domain_id);
        if ($domain->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $created = [];
        foreach ($request->records as $record) {
            $record['domain_id'] = $request->domain_id;
            $created[] = DnsSetting::create($record);
        }

        return response()->json([
            'message' => count($created) . ' DNS records created successfully',
            'dns_settings' => $created,
        ], 201);
    }
}
