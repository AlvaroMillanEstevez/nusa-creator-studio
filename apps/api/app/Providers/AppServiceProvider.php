<?php

namespace App\Providers;

use App\Contracts\AffiliateCampaignProvider;
use App\Contracts\AiVoiceoverProvider;
use App\Contracts\ProductVideoScriptProvider;
use App\Contracts\VideoEnhancerProvider;
use App\Services\Generation\MockAffiliateCampaignProvider;
use App\Services\Generation\MockAiVoiceoverProvider;
use App\Services\Generation\MockProductVideoScriptProvider;
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
        $this->app->bind(ProductVideoScriptProvider::class, MockProductVideoScriptProvider::class);
        $this->app->bind(AffiliateCampaignProvider::class, MockAffiliateCampaignProvider::class);
        $this->app->bind(AiVoiceoverProvider::class, MockAiVoiceoverProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
