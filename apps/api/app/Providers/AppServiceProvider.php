<?php

namespace App\Providers;

use App\Contracts\AffiliateCampaignProvider;
use App\Contracts\VideoEnhancerProvider;
use App\Services\Generation\MockAffiliateCampaignProvider;
use App\Services\Generation\MockVideoEnhancerProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VideoEnhancerProvider::class, MockVideoEnhancerProvider::class);
        $this->app->bind(AffiliateCampaignProvider::class, MockAffiliateCampaignProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
