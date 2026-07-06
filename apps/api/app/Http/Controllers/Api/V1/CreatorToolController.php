<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreatorToolResource;
use App\Models\CreatorTool;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CreatorToolController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $tools = CreatorTool::query()
            ->where('is_visible', true)
            ->whereIn('status', ['available', 'coming_soon', 'maintenance'])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return CreatorToolResource::collection($tools);
    }
}
