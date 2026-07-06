<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionPlanController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $plans = SubscriptionPlan::query()
            ->where('is_public', true)
            ->where('is_active', true)
            ->where('is_internal', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return SubscriptionPlanResource::collection($plans);
    }
}
