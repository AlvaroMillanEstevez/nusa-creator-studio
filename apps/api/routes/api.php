<?php

use App\Http\Controllers\Api\V1\Admin\AdminCreatorToolController;
use App\Http\Controllers\Api\V1\Admin\AdminSubscriptionPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminSummaryController;
use App\Http\Controllers\Api\V1\Admin\AdminUserAccessController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CreatorToolController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GenerateAiVoiceoverController;
use App\Http\Controllers\Api\V1\GenerateCampaignController;
use App\Http\Controllers\Api\V1\GenerateFashionTryOnController;
use App\Http\Controllers\Api\V1\GenerateMotionAiVideoController;
use App\Http\Controllers\Api\V1\GenerateProductVideoScriptController;
use App\Http\Controllers\Api\V1\GenerateVideoEnhancerController;
use App\Http\Controllers\Api\V1\GenerationController;
use App\Http\Controllers\Api\V1\MediaAssetController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectGenerationController;
use App\Http\Controllers\Api\V1\ProjectMediaAssetController;
use App\Http\Controllers\Api\V1\SubscriptionPlanController;
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
            Route::patch('profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');
            Route::patch('password', [AuthController::class, 'updatePassword'])->name('auth.password.update');
        });
    });

    Route::get('tools', CreatorToolController::class)->name('tools.index');
    Route::get('plans', SubscriptionPlanController::class)->name('plans.index');

    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'admin'])
        ->name('admin.')
        ->group(function () {
            Route::get('summary', AdminSummaryController::class)->name('summary');
            Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
            Route::patch('users/{user}/access', AdminUserAccessController::class)
                ->name('users.access.update');
            Route::get('tools', [AdminCreatorToolController::class, 'index'])
                ->name('tools.index');
            Route::get('tools/{tool}', [AdminCreatorToolController::class, 'show'])
                ->name('tools.show');
            Route::patch('tools/{tool}', [AdminCreatorToolController::class, 'update'])
                ->name('tools.update');
            Route::get('plans', [AdminSubscriptionPlanController::class, 'index'])
                ->name('plans.index');
            Route::get('plans/{plan}', [AdminSubscriptionPlanController::class, 'show'])
                ->name('plans.show');
            Route::patch('plans/{plan}', [AdminSubscriptionPlanController::class, 'update'])
                ->name('plans.update');
        });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard.show');
        Route::get('generations/{generation}', GenerationController::class)
            ->name('generations.show');
        Route::get(
            'projects/{project}/media-assets',
            ProjectMediaAssetController::class,
        )->name('projects.media-assets.index');
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
        Route::post(
            'projects/{project}/generate-voiceover',
            GenerateAiVoiceoverController::class,
        )->name('projects.generate-voiceover');
        Route::post(
            'projects/{project}/generate-fashion-try-on',
            GenerateFashionTryOnController::class,
        )->name('projects.generate-fashion-try-on');
        Route::post(
            'projects/{project}/generate-motion-video',
            GenerateMotionAiVideoController::class,
        )->name('projects.generate-motion-video');
        Route::get('media-assets', [MediaAssetController::class, 'index'])
            ->name('media-assets.index');
        Route::post('media-assets', [MediaAssetController::class, 'store'])
            ->name('media-assets.store');
        Route::delete('media-assets/{mediaAsset}', [MediaAssetController::class, 'destroy'])
            ->name('media-assets.destroy');
        Route::apiResource('projects', ProjectController::class);
    });

    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'version' => 'v1',
        ]);
    });
});
