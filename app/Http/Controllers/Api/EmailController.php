<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class EmailController extends Controller
{
    /**
     * List all email accounts for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $emailAccounts = EmailAccount::where('user_id', $request->user()->id)
            ->with('domain')
            ->paginate($request->get('per_page', 15));

        return response()->json($emailAccounts);
    }

    /**
     * Get a specific email account
     */
    public function show(Request $request, EmailAccount $emailAccount): JsonResponse
    {
        if ($emailAccount->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $emailAccount->load('domain');

        return response()->json($emailAccount);
    }

    /**
     * Create a new email account
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|exists:domains,id',
            'email_address' => 'required|email|max:255|unique:email_accounts,email_address',
            'password' => 'required|string|min:8',
            'quota' => 'nullable|integer|min:0',
            'forwarding_rules' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;
        $data['password'] = Hash::make($data['password']);

        $emailAccount = EmailAccount::create($data);

        return response()->json([
            'message' => 'Email account created successfully',
            'email_account' => $emailAccount->load('domain'),
        ], 201);
    }

    /**
     * Update an email account
     */
    public function update(Request $request, EmailAccount $emailAccount): JsonResponse
    {
        if ($emailAccount->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'sometimes|string|min:8',
            'quota' => 'nullable|integer|min:0',
            'forwarding_rules' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $emailAccount->update($data);

        return response()->json([
            'message' => 'Email account updated successfully',
            'email_account' => $emailAccount->fresh(['domain']),
        ]);
    }

    /**
     * Delete an email account
     */
    public function destroy(Request $request, EmailAccount $emailAccount): JsonResponse
    {
        if ($emailAccount->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $emailAccount->delete();

        return response()->json([
            'message' => 'Email account deleted successfully'
        ]);
    }
}
