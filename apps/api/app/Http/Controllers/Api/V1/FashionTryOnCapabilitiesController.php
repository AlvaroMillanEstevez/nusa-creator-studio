<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\FashionTryOnCapabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FashionTryOnCapabilitiesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => FashionTryOnCapabilities::forUser($request->user()),
        ]);
    }
}
