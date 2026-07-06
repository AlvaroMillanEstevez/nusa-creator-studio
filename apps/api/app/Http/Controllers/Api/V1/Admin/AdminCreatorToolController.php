<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCreatorToolRequest;
use App\Http\Resources\AdminCreatorToolResource;
use App\Models\CreatorTool;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCreatorToolController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $tools = CreatorTool::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return AdminCreatorToolResource::collection($tools);
    }

    public function show(CreatorTool $tool): AdminCreatorToolResource
    {
        return new AdminCreatorToolResource($tool);
    }

    public function update(
        UpdateCreatorToolRequest $request,
        CreatorTool $tool,
    ): AdminCreatorToolResource {
        $tool->update($request->validated());

        return new AdminCreatorToolResource($tool->refresh());
    }
}
