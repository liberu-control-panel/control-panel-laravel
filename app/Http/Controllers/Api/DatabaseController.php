<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Database;
use App\Models\DatabaseUser;
use App\Services\MySqlDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DatabaseController extends Controller
{
    protected MySqlDatabaseService $service;

    public function __construct(MySqlDatabaseService $service)
    {
        $this->service = $service;
    }

    /**
     * List all databases for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $databases = Database::where('user_id', $request->user()->id)
            ->with(['domain', 'databaseUsers'])
            ->paginate($request->get('per_page', 15));

        return response()->json($databases);
    }

    /**
     * Get a specific database
     */
    public function show(Request $request, Database $database): JsonResponse
    {
        if ($database->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $database->load(['domain', 'databaseUsers']);

        return response()->json($database);
    }

    /**
     * Create a new database with auto-provisioned user
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:64|alpha_dash',
            'domain_id' => 'nullable|exists:domains,id',
            'engine' => 'required|string|in:mysql,postgresql,mariadb',
            'charset' => 'nullable|string|max:50',
            'collation' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $user = $request->user();

        // Prefix database name with username
        $username = $user->username ?? 'user_' . $user->id;
        $dbName = $username . '_' . $data['name'];

        // Set defaults
        $engine = $data['engine'] ?? Database::ENGINE_MARIADB;
        $charset = $data['charset'] ?? Database::getDefaultCharset($engine);
        $collation = $data['collation'] ?? Database::getDefaultCollation($engine);

        // Create database
        $result = $this->service->createDatabase($dbName, $charset, $collation);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 500);
        }

        // Create database record
        $database = Database::create([
            'user_id' => $user->id,
            'domain_id' => $data['domain_id'] ?? null,
            'name' => $dbName,
            'engine' => $engine,
            'charset' => $charset,
            'collation' => $collation,
            'is_active' => true,
        ]);

        // Auto-create database user
        $dbUsername = $dbName;
        $password = Str::random(16);

        $userResult = $this->service->createUser($dbUsername, $password, 'localhost');

        if ($userResult['success']) {
            // Grant all privileges
            $this->service->grantPrivileges($dbUsername, $dbName, ['ALL']);

            // Save database user record
            DatabaseUser::create([
                'database_id' => $database->id,
                'user_id' => $user->id,
                'username' => $dbUsername,
                'host' => 'localhost',
                'privileges' => ['ALL'],
            ]);
        }

        return response()->json([
            'message' => 'Database created successfully',
            'database' => $database->load('databaseUsers'),
            'credentials' => [
                'database' => $dbName,
                'username' => $dbUsername,
                'password' => $password,
                'host' => 'localhost',
            ],
        ], 201);
    }

    /**
     * Delete a database
     */
    public function destroy(Request $request, Database $database): JsonResponse
    {
        if ($database->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Drop the database
        $result = $this->service->dropDatabase($database->name);

        if ($result['success']) {
            $database->delete();

            return response()->json([
                'message' => 'Database deleted successfully'
            ]);
        }

        return response()->json([
            'error' => $result['message']
        ], 500);
    }
}
