<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriptionPlanRequest;
use App\Http\Resources\AdminSubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminSubscriptionPlanController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $plans = SubscriptionPlan::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return AdminSubscriptionPlanResource::collection($plans);
    }

    public function show(SubscriptionPlan $plan): AdminSubscriptionPlanResource
    {
        return new AdminSubscriptionPlanResource($plan);
    }

    public function update(
        UpdateSubscriptionPlanRequest $request,
        SubscriptionPlan $plan,
    ): AdminSubscriptionPlanResource {
        $plan->update($request->validated());

        return new AdminSubscriptionPlanResource($plan->refresh());
    }
}
