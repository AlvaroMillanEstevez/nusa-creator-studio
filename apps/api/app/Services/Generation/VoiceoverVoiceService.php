<?php

namespace App\Services\Generation;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VoiceoverVoiceService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(User $user): array
    {
        if (! $this->shouldUseRealVoiceList($user)) {
            return $this->mockVoices();
        }

        return Cache::remember('voiceover.voices.elevenlabs.safe', now()->addMinutes(10), function (): array {
            return $this->fetchElevenLabsVoices();
        });
    }

    private function shouldUseRealVoiceList(User $user): bool
    {
        if (config('voiceover.provider', 'mock') !== 'elevenlabs' || ! (bool) config('voiceover.real_enabled', false)) {
            return false;
        }

        if ((string) config('voiceover.elevenlabs.api_key', '') === '') {
            return false;
        }

        if (! (bool) config('voiceover.real_internal_only', true)) {
            return true;
        }

        return $user->isAdmin()
            || $user->isInternalCreator()
            || $user->hasUnlimitedAccess()
            || $user->plan_key === 'internal_unlimited';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchElevenLabsVoices(): array
    {
        try {
            $response = Http::baseUrl((string) config('voiceover.elevenlabs.base_url'))
                ->timeout((int) config('voiceover.elevenlabs.timeout_seconds', 60))
                ->acceptJson()
                ->withHeaders([
                    'xi-api-key' => (string) config('voiceover.elevenlabs.api_key'),
                ])
                ->get('/v2/voices', [
                    'page_size' => 30,
                ]);
        } catch (ConnectionException) {
            return $this->mockVoices();
        }

        if (! $response->successful()) {
            return $this->mockVoices();
        }

        $voices = $response->json('voices');

        if (! is_array($voices)) {
            return $this->mockVoices();
        }

        return collect($voices)
            ->filter(fn (mixed $voice): bool => is_array($voice))
            ->map(fn (array $voice): array => [
                'provider' => 'elevenlabs',
                'voice_id' => is_string($voice['voice_id'] ?? null) ? $voice['voice_id'] : '',
                'name' => is_string($voice['name'] ?? null) ? $voice['name'] : 'Untitled voice',
                'category' => is_string($voice['category'] ?? null) ? $voice['category'] : null,
                'labels' => is_array($voice['labels'] ?? null) ? $voice['labels'] : null,
                'preview_url' => is_string($voice['preview_url'] ?? null) ? $voice['preview_url'] : null,
            ])
            ->filter(fn (array $voice): bool => $voice['voice_id'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mockVoices(): array
    {
        return [[
            'provider' => 'mock',
            'voice_id' => 'default',
            'name' => 'Default voice',
            'category' => 'default',
            'labels' => [
                'style' => 'general',
            ],
            'preview_url' => null,
        ]];
    }
}
