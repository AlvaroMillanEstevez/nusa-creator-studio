<?php

use App\Http\Controllers\Api\V1\GenerateCampaignController;
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
    Route::apiResource('projects', ProjectController::class);

    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'version' => 'v1',
        ]);
    });
});
