<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Server;
use App\Models\ServerCredential;
use App\Services\SshConnectionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SshController extends Controller
{
    protected SshConnectionService $sshService;

    public function __construct(SshConnectionService $sshService)
    {
        $this->sshService = $sshService;
    }

    /**
     * Generate SSH key pair
     */
    public function generateKeyPair(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'passphrase' => 'nullable|string|min:8',
            'bits' => 'nullable|integer|in:2048,4096',
            'comment' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $keyPair = $this->sshService->generateKeyPair(
                $data['passphrase'] ?? '',
                $data['bits'] ?? 2048
            );

            // Add comment to public key if provided
            if (!empty($data['comment'])) {
                $keyPair['public_key'] = trim($keyPair['public_key']) . ' ' . $data['comment'];
            }

            return response()->json([
                'message' => 'SSH key pair generated successfully',
                'public_key' => $keyPair['public_key'],
                'private_key' => $keyPair['private_key'],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to generate SSH key pair: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to generate SSH key pair: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deploy SSH public key to a domain
     */
    public function deployKeyToDomain(Request $request, Domain $domain): JsonResponse
    {
        if ($domain->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'public_key' => 'required|string',
            'username' => 'required|string|alpha_dash|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            // Update domain with SSH credentials
            $domain->update([
                'ssh_username' => $data['username'],
            ]);

            // If domain has an associated server, deploy the key
            if ($domain->server_id) {
                $server = Server::find($domain->server_id);
                
                if ($server) {
                    $this->deployPublicKeyToServer($server, $data['username'], $data['public_key']);
                }
            }

            return response()->json([
                'message' => 'SSH key deployed successfully',
                'domain' => $domain->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to deploy SSH key to domain: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to deploy SSH key: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deploy SSH public key to a server
     */
    public function deployKeyToServer(Request $request, Server $server): JsonResponse
    {
        if ($server->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'public_key' => 'required|string',
            'username' => 'required|string|alpha_dash|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $this->deployPublicKeyToServer($server, $data['username'], $data['public_key']);

            return response()->json([
                'message' => 'SSH key deployed successfully to server',
                'server' => $server->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to deploy SSH key to server: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to deploy SSH key: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test SSH connection
     */
    public function testConnection(Request $request, Server $server): JsonResponse
    {
        if ($server->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $success = $this->sshService->testConnection($server);

            if ($success) {
                return response()->json([
                    'message' => 'SSH connection successful',
                    'connected' => true,
                ]);
            } else {
                return response()->json([
                    'message' => 'SSH connection failed',
                    'connected' => false,
                ], 503);
            }
        } catch (\Exception $e) {
            Log::error('SSH connection test failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'SSH connection test failed: ' . $e->getMessage(),
                'connected' => false,
            ], 500);
        }
    }

    /**
     * Create server credential with SSH key
     */
    public function createCredential(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'server_id' => 'required|exists:servers,id',
            'username' => 'required|string|alpha_dash|max:32',
            'auth_type' => 'required|string|in:password,ssh_key',
            'password' => 'required_if:auth_type,password|nullable|string',
            'ssh_private_key' => 'required_if:auth_type,ssh_key|nullable|string',
            'ssh_public_key' => 'nullable|string',
            'ssh_key_passphrase' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        
        // Verify server ownership
        $server = Server::findOrFail($data['server_id']);
        if ($server->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $credential = ServerCredential::create([
                'server_id' => $data['server_id'],
                'username' => $data['username'],
                'password' => $data['password'] ?? null,
                'ssh_private_key' => $data['ssh_private_key'] ?? null,
                'ssh_public_key' => $data['ssh_public_key'] ?? null,
                'ssh_key_passphrase' => $data['ssh_key_passphrase'] ?? null,
                'auth_type' => $data['auth_type'],
                'is_active' => $data['is_active'] ?? false,
            ]);

            return response()->json([
                'message' => 'Server credential created successfully',
                'credential' => $credential,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create server credential: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to create credential: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deploy public key to server's authorized_keys
     */
    protected function deployPublicKeyToServer(Server $server, string $username, string $publicKey): void
    {
        // Create .ssh directory if not exists
        $this->sshService->execute($server, "mkdir -p /home/{$username}/.ssh");
        $this->sshService->execute($server, "chmod 700 /home/{$username}/.ssh");

        // Append public key to authorized_keys
        $escapedKey = escapeshellarg($publicKey);
        $this->sshService->execute(
            $server, 
            "echo {$escapedKey} >> /home/{$username}/.ssh/authorized_keys"
        );

        // Set proper permissions
        $this->sshService->execute($server, "chmod 600 /home/{$username}/.ssh/authorized_keys");
        $this->sshService->execute($server, "chown -R {$username}:{$username} /home/{$username}/.ssh");
    }
}
