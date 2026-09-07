<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Liberu\ControlPanel\KubernetesApi\Http\Controllers\ClusterController;
use Liberu\ControlPanel\KubernetesApi\Http\Controllers\NodeController;

Route::prefix('api/v1/control-panel/kubernetes')->middleware(['api', 'auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/', [ClusterController::class, 'index'])->name('control-panel.kubernetes.index');
    Route::post('/', [ClusterController::class, 'store'])->name('control-panel.kubernetes.store');
    Route::get('/assets', [ClusterController::class, 'assets'])->name('control-panel.kubernetes.assets.index');
    Route::post('/resources', [ClusterController::class, 'resourceRecord'])->name('control-panel.kubernetes.resources.store');
    Route::post('/assets', [ClusterController::class, 'asset'])->name('control-panel.kubernetes.assets.store');
    Route::post('/workloads/{workload}/reconcile', [ClusterController::class, 'reconcileWorkload'])->name('control-panel.kubernetes.workloads.reconcile');
    Route::post('/helm-releases/{release}/rollback', [ClusterController::class, 'rollbackHelmRelease'])->name('control-panel.kubernetes.helm-releases.rollback');
    Route::post('/nodes/{node}/cordon', [NodeController::class, 'cordon'])->name('control-panel.kubernetes.nodes.cordon');
    Route::post('/nodes/{node}/uncordon', [NodeController::class, 'uncordon'])->name('control-panel.kubernetes.nodes.uncordon');
    Route::post('/nodes/{node}/drain', [NodeController::class, 'drain'])->name('control-panel.kubernetes.nodes.drain');
    Route::post('/nodes/{node}/label', [NodeController::class, 'label'])->name('control-panel.kubernetes.nodes.label');
    Route::post('/nodes/{node}/unlabel', [NodeController::class, 'unlabel'])->name('control-panel.kubernetes.nodes.unlabel');
    Route::post('{cluster}/suspend', [ClusterController::class, 'suspend'])->name('control-panel.kubernetes.suspend');
    Route::post('{cluster}/archive', [ClusterController::class, 'archive'])->name('control-panel.kubernetes.archive');
    Route::get('{cluster}', [ClusterController::class, 'show'])->name('control-panel.kubernetes.show');
});
