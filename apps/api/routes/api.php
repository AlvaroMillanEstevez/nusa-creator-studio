<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GenerateCampaignController;
use App\Http\Controllers\Api\V1\GenerateProductVideoScriptController;
use App\Http\Controllers\Api\V1\GenerateVideoEnhancerController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectGenerationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'environment' => app()->environment(),
    ]);
});

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard.show');
        Route::get(
            'projects/{project}/generations',
            ProjectGenerationController::class,
        )->name('projects.generations.index');
        Route::post(
            'projects/{project}/generate-campaign',
            GenerateCampaignController::class,
        )->name('projects.generate-campaign');
        Route::post(
            'projects/{project}/generate-video-enhancer',
            GenerateVideoEnhancerController::class,
        )->name('projects.generate-video-enhancer');
        Route::post(
            'projects/{project}/generate-product-video-script',
            GenerateProductVideoScriptController::class,
        )->name('projects.generate-product-video-script');
        Route::apiResource('projects', ProjectController::class);
    });

    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'version' => 'v1',
        ]);
    });
});
