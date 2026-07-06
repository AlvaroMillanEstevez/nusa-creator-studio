<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardSummaryService;
use App\Support\CurrentUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CurrentUserResolver $currentUser,
        private readonly DashboardSummaryService $dashboard,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(
            $this->dashboard->forUser($this->currentUser->resolve($request)),
        );
    }
}
