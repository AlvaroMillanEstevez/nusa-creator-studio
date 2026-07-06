<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSummaryService;
use Illuminate\Http\JsonResponse;

class AdminSummaryController extends Controller
{
    public function __invoke(AdminSummaryService $summary): JsonResponse
    {
        return response()->json($summary->get());
    }
}
