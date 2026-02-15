<?php

use App\Http\Controllers\Api\DatabaseController;
use App\Http\Controllers\Api\DnsController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VirtualHostController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // User endpoints
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/me', [UserController::class, 'me']);
    Route::put('/me', [UserController::class, 'update']);
    Route::get('/statistics', [UserController::class, 'statistics']);
    
    // API Token management
    Route::post('/tokens', [UserController::class, 'createToken']);
    Route::get('/tokens', [UserController::class, 'tokens']);
    Route::delete('/tokens/{tokenId}', [UserController::class, 'revokeToken']);

    // Virtual Host management
    Route::apiResource('virtual-hosts', VirtualHostController::class);

    // Database management
    Route::apiResource('databases', DatabaseController::class)->except(['update']);

    // Email management
    Route::apiResource('emails', EmailController::class);

    // DNS management
    Route::apiResource('dns', DnsController::class);
    Route::post('/dns/bulk', [DnsController::class, 'bulkStore']);
});

// Webhook routes for Git deployments (no auth required, validated via signature)
Route::prefix('webhooks')->group(function () {
    Route::post('github/{deployment}', [WebhookController::class, 'github'])->name('webhooks.github');
    Route::post('gitlab/{deployment}', [WebhookController::class, 'gitlab'])->name('webhooks.gitlab');
    Route::post('generic/{deployment}', [WebhookController::class, 'generic'])->name('webhooks.generic');
});
