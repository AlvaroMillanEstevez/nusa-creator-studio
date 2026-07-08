<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Generation\VoiceoverVoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceoverVoiceController extends Controller
{
    public function __construct(private readonly VoiceoverVoiceService $voices) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->voices->list($request->user()),
        ]);
    }
}
