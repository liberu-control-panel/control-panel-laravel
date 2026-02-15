<?php

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Webhook routes for Git deployments (no auth required, validated via signature)
Route::prefix('webhooks')->group(function () {
    Route::post('github/{deployment}', [WebhookController::class, 'github'])->name('webhooks.github');
    Route::post('gitlab/{deployment}', [WebhookController::class, 'gitlab'])->name('webhooks.gitlab');
    Route::post('generic/{deployment}', [WebhookController::class, 'generic'])->name('webhooks.generic');
});
