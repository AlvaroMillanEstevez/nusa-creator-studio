<?php

namespace App\Providers;

use App\Contracts\AiVoiceoverProvider;
use App\Contracts\AutoVideoEditorProvider;
use App\Contracts\CampaignGenerationProvider;
use App\Contracts\FashionTryOnProvider;
use App\Contracts\MotionAiVideoProvider;
use App\Contracts\ProductVideoScriptProvider;
use App\Contracts\VideoEnhancerProvider;
use App\Contracts\VideoToCampaignProvider;
use App\Services\Generation\AiVoiceoverProviderManager;
use App\Services\Generation\CampaignGenerationProviderManager;
use App\Services\Generation\MockAutoVideoEditorProvider;
use App\Services\Generation\MockFashionTryOnProvider;
use App\Services\Generation\MockMotionAiVideoProvider;
use App\Services\Generation\MockProductVideoScriptProvider;
use App\Services\Generation\MockVideoEnhancerProvider;
use App\Services\Generation\MockVideoToCampaignProvider;
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
        $this->app->bind(CampaignGenerationProvider::class, CampaignGenerationProviderManager::class);
        $this->app->bind(AiVoiceoverProvider::class, AiVoiceoverProviderManager::class);
        $this->app->bind(FashionTryOnProvider::class, MockFashionTryOnProvider::class);
        $this->app->bind(MotionAiVideoProvider::class, MockMotionAiVideoProvider::class);
        $this->app->bind(VideoToCampaignProvider::class, MockVideoToCampaignProvider::class);
        $this->app->bind(AutoVideoEditorProvider::class, MockAutoVideoEditorProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
