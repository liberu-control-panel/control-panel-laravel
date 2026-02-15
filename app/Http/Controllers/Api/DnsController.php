<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DnsSetting;
use App\Models\Domain;
use App\Services\DnsService;
use App\Rules\ValidDnsRecord;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DnsController extends Controller
{
    protected $dnsService;

    public function __construct(DnsService $dnsService)
    {
        $this->dnsService = $dnsService;
    }

    /**
     * List all DNS settings for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = DnsSetting::whereHas('domain', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with('domain');

        // Apply filters
        if ($request->has('domain_id')) {
            $query->where('domain_id', $request->domain_id);
        }

        if ($request->has('record_type')) {
            $query->where('record_type', $request->record_type);
        }

        $dnsSettings = $query->paginate($request->get('per_page', 15));

        return response()->json($dnsSettings);
    }

    /**
     * Get DNS settings for a specific domain
     */
    public function show(Request $request, DnsSetting $dnsSetting): JsonResponse
    {
        if ($dnsSetting->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to DNS record'
            ], 403);
        }

        $dnsSetting->load('domain');

        return response()->json([
            'success' => true,
            'data' => $dnsSetting
        ]);
    }

    /**
     * Create a new DNS record
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|exists:domains,id',
            'record_type' => 'required|string|in:A,AAAA,CNAME,MX,TXT,NS,PTR,SRV',
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^(@|[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)$/'
            ],
            'value' => 'required|string|max:1000',
            'ttl' => 'nullable|integer|min:60|max:86400',
            'priority' => 'nullable|integer|min:0|max:65535',
        ], [
            'name.regex' => 'Invalid record name. Use @ for root or a valid subdomain.',
            'ttl.min' => 'TTL must be at least 60 seconds.',
            'ttl.max' => 'TTL must not exceed 86400 seconds (24 hours).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // Verify domain ownership
        $domain = Domain::findOrFail($data['domain_id']);
        if ($domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to domain'
            ], 403);
        }

        // Additional validation based on record type
        $recordValidator = new ValidDnsRecord($data['record_type']);
        if (!$recordValidator->passes('value', $data['value'])) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['value' => [$recordValidator->message()]]
            ], 422);
        }

        // Validate MX record priority
        if ($data['record_type'] === 'MX' && !isset($data['priority'])) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['priority' => ['Priority is required for MX records']]
            ], 422);
        }

        try {
            // Use DNS service to create record
            $dnsSetting = $this->dnsService->addDnsRecord($domain, $data);

            if (!$dnsSetting) {
                throw new \Exception('Failed to create DNS record');
            }

            return response()->json([
                'success' => true,
                'message' => 'DNS record created successfully',
                'data' => $dnsSetting->load('domain'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create DNS record: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create DNS record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a DNS record
     */
    public function update(Request $request, DnsSetting $dnsSetting): JsonResponse
    {
        if ($dnsSetting->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to DNS record'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'record_type' => 'sometimes|string|in:A,AAAA,CNAME,MX,TXT,NS,PTR,SRV',
            'name' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^(@|[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)$/'
            ],
            'value' => 'sometimes|string|max:1000',
            'ttl' => 'nullable|integer|min:60|max:86400',
            'priority' => 'nullable|integer|min:0|max:65535',
        ], [
            'name.regex' => 'Invalid record name. Use @ for root or a valid subdomain.',
            'ttl.min' => 'TTL must be at least 60 seconds.',
            'ttl.max' => 'TTL must not exceed 86400 seconds (24 hours).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Additional validation if record type or value is being updated
        if (isset($data['value']) || isset($data['record_type'])) {
            $recordType = $data['record_type'] ?? $dnsSetting->record_type;
            $value = $data['value'] ?? $dnsSetting->value;
            
            $recordValidator = new ValidDnsRecord($recordType);
            if (!$recordValidator->passes('value', $value)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['value' => [$recordValidator->message()]]
                ], 422);
            }
        }

        try {
            // Use DNS service to update record
            $success = $this->dnsService->updateDnsRecord($dnsSetting, $data);

            if (!$success) {
                throw new \Exception('Failed to update DNS record');
            }

            return response()->json([
                'success' => true,
                'message' => 'DNS record updated successfully',
                'data' => $dnsSetting->fresh(['domain']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update DNS record: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update DNS record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a DNS record
     */
    public function destroy(Request $request, DnsSetting $dnsSetting): JsonResponse
    {
        if ($dnsSetting->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to DNS record'
            ], 403);
        }

        try {
            // Use DNS service to delete record
            $success = $this->dnsService->deleteDnsRecord($dnsSetting);

            if (!$success) {
                throw new \Exception('Failed to delete DNS record');
            }

            return response()->json([
                'success' => true,
                'message' => 'DNS record deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete DNS record: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete DNS record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create DNS records (useful for zone imports)
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|exists:domains,id',
            'records' => 'required|array|min:1|max:50',
            'records.*.record_type' => 'required|string|in:A,AAAA,CNAME,MX,TXT,NS,PTR,SRV',
            'records.*.name' => [
                'required',
                'string',
                'max:255',
                'regex:/^(@|[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)$/'
            ],
            'records.*.value' => 'required|string|max:1000',
            'records.*.ttl' => 'nullable|integer|min:60|max:86400',
            'records.*.priority' => 'nullable|integer|min:0|max:65535',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify domain ownership
        $domain = Domain::findOrFail($request->domain_id);
        if ($domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to domain'
            ], 403);
        }

        $created = [];
        $errors = [];

        foreach ($request->records as $index => $record) {
            try {
                // Validate record value
                $recordValidator = new ValidDnsRecord($record['record_type']);
                if (!$recordValidator->passes('value', $record['value'])) {
                    $errors["records.{$index}.value"] = $recordValidator->message();
                    continue;
                }

                $record['domain_id'] = $request->domain_id;
                $dnsSetting = $this->dnsService->addDnsRecord($domain, $record);
                
                if ($dnsSetting) {
                    $created[] = $dnsSetting;
                } else {
                    $errors["records.{$index}"] = 'Failed to create DNS record';
                }
            } catch (\Exception $e) {
                $errors["records.{$index}"] = $e->getMessage();
            }
        }

        if (!empty($errors) && empty($created)) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create any DNS records',
                'errors' => $errors
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' DNS record(s) created successfully',
            'data' => $created,
            'errors' => $errors
        ], !empty($errors) ? 207 : 201);
    }

    /**
     * Test DNS resolution for a domain
     */
    public function testResolution(Request $request, Domain $domain): JsonResponse
    {
        if ($domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to domain'
            ], 403);
        }

        $recordType = $request->get('record_type', 'A');
        
        $result = $this->dnsService->testDnsResolution($domain, $recordType);

        return response()->json([
            'success' => $result['success'],
            'data' => $result
        ]);
    }

    /**
     * Check DNS propagation status
     */
    public function checkPropagation(Request $request, Domain $domain): JsonResponse
    {
        if ($domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to domain'
            ], 403);
        }

        $status = $this->dnsService->getDnsPropagationStatus($domain);

        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * Validate DNS record before creation
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'record_type' => 'required|string|in:A,AAAA,CNAME,MX,TXT,NS,PTR,SRV',
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^(@|[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)$/'
            ],
            'value' => 'required|string|max:1000',
            'ttl' => 'nullable|integer|min:60|max:86400',
            'priority' => 'nullable|integer|min:0|max:65535',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // Additional validation based on record type
        $recordValidator = new ValidDnsRecord($data['record_type']);
        if (!$recordValidator->passes('value', $data['value'])) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'errors' => ['value' => [$recordValidator->message()]]
            ], 422);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'message' => 'DNS record is valid'
        ]);
    }
}
