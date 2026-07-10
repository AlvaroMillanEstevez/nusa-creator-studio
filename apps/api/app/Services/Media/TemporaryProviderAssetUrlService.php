<?php

namespace App\Services\Media;

use App\Exceptions\AutoVideoEditor\AutoVideoEditorProviderException;
use App\Models\MediaAsset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TemporaryProviderAssetUrlService
{
    private const CACHE_PREFIX = 'provider_asset_url:';

    public function create(MediaAsset $asset, string $purpose = 'shotstack'): string
    {
        $baseUrl = $this->publicBaseUrl();
        $token = Str::random(72);
        $expiresAt = now()->addMinutes($this->ttlMinutes());

        Cache::put($this->cacheKey($token), [
            'media_asset_id' => $asset->getKey(),
            'user_id' => $asset->user_id,
            'project_id' => $asset->project_id,
            'purpose' => $purpose,
            'expires_at' => $expiresAt->toISOString(),
        ], $expiresAt);

        return $baseUrl.'/api/v1/provider-assets/'.$token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(string $token, string $purpose = 'shotstack'): ?array
    {
        $payload = Cache::get($this->cacheKey($token));

        if (! is_array($payload)) {
            return null;
        }

        if (($payload['purpose'] ?? null) !== $purpose) {
            return null;
        }

        $expiresAt = is_string($payload['expires_at'] ?? null)
            ? Carbon::parse($payload['expires_at'])
            : null;

        if ($expiresAt === null || $expiresAt->isPast()) {
            Cache::forget($this->cacheKey($token));

            return null;
        }

        return $payload;
    }

    public function ttlMinutes(): int
    {
        return max(1, (int) config('auto_video_editor.provider_assets.ttl_minutes', 30));
    }

    public function publicBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('auto_video_editor.provider_assets.public_base_url', ''), '/');

        if ($baseUrl === '') {
            throw AutoVideoEditorProviderException::missingConfiguration('shotstack_public_asset_base_url_missing', [
                'provider' => 'shotstack',
                'stage' => 'asset_url',
            ]);
        }

        $parts = parse_url($baseUrl);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';

        if ($scheme !== 'https' || $this->isLocalHost($host)) {
            throw AutoVideoEditorProviderException::missingConfiguration('shotstack_public_asset_base_url_missing', [
                'provider' => 'shotstack',
                'stage' => 'asset_url',
                'public_base_url_status' => $scheme === 'https' ? 'local_or_private_host' : 'not_https',
            ]);
        }

        return $baseUrl;
    }

    private function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX.hash('sha256', $token);
    }

    private function isLocalHost(string $host): bool
    {
        return $host === ''
            || $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '0.0.0.0'
            || $host === '::1'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local');
    }
}
