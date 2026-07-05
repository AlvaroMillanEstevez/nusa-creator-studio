<?php

use App\Http\Controllers\Api\V1\ProjectController;
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
    Route::apiResource('projects', ProjectController::class);

    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'version' => 'v1',
        ]);
    });
});
