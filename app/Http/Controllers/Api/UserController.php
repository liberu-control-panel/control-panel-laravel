<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Get authenticated user details
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['currentTeam', 'teams']);

        return response()->json($user);
    }

    /**
     * Update authenticated user profile
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $request->user()->id,
            'username' => 'sometimes|string|max:255|unique:users,username,' . $request->user()->id,
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
            unset($data['password_confirmation']);
        }

        $request->user()->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $request->user()->fresh(),
        ]);
    }

    /**
     * Get user statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = [
            'virtual_hosts' => $user->id ? \App\Models\VirtualHost::where('user_id', $user->id)->count() : 0,
            'databases' => $user->id ? \App\Models\Database::where('user_id', $user->id)->count() : 0,
            'domains' => $user->id ? \App\Models\Domain::where('user_id', $user->id)->count() : 0,
            'email_accounts' => $user->id ? \App\Models\EmailAccount::where('user_id', $user->id)->count() : 0,
            'dns_records' => $user->id ? \App\Models\DnsSetting::whereHas('domain', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->count() : 0,
        ];

        return response()->json($stats);
    }

    /**
     * Create API token
     */
    public function createToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $token = $request->user()->createToken(
            $validator->validated()['name'],
            $validator->validated()['abilities'] ?? ['*']
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'name' => $validator->validated()['name'],
        ], 201);
    }

    /**
     * List user API tokens
     */
    public function tokens(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens;

        return response()->json($tokens);
    }

    /**
     * Revoke API token
     */
    public function revokeToken(Request $request, $tokenId): JsonResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return response()->json([
            'message' => 'Token revoked successfully'
        ]);
    }
}
