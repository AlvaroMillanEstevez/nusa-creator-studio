<?php

namespace App\Services\Generation;

use App\Data\Voiceover\AiVoiceoverRequestData;
use App\Data\Voiceover\AiVoiceoverResultData;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GeneratedVoiceoverMediaService
{
    public function store(
        User $user,
        Project $project,
        AiVoiceoverRequestData $requestData,
        AiVoiceoverResultData $resultData,
    ): MediaAsset {
        if ($resultData->audioBinary === null || $resultData->audioBinary === '') {
            throw new RuntimeException('Generated audio content is empty.');
        }

        $disk = (string) config('media.disk', 'local');

        if ($disk === '' || $disk === 'public') {
            throw new RuntimeException('Generated media storage must use a configured private disk.');
        }

        $directory = sprintf(
            'users/%d/generated/voiceovers/%s/%s',
            $user->getKey(),
            now()->format('Y'),
            now()->format('m'),
        );
        $filename = sprintf(
            'voiceover-%d-%s.%s',
            $requestData->generationId,
            Str::uuid(),
            $resultData->extension,
        );
        $path = "{$directory}/{$filename}";

        $stored = Storage::disk($disk)->put($path, $resultData->audioBinary, [
            'visibility' => 'private',
        ]);

        if ($stored === false) {
            throw new RuntimeException('Generated audio could not be stored.');
        }

        return $user->mediaAssets()->create([
            'project_id' => $project->getKey(),
            'type' => 'audio',
            'usage_role' => 'audio_track',
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'voiceover-'.$requestData->generationId.'.'.$resultData->extension,
            'mime_type' => $resultData->mimeType,
            'size_bytes' => strlen($resultData->audioBinary),
            'metadata' => array_filter([
                'generated' => true,
                'provider' => $resultData->provider,
                'provider_model' => $resultData->providerModel,
                'provider_voice_id' => $resultData->providerVoiceId,
                'provider_request_id' => $resultData->providerRequestId,
                'generation_id' => $requestData->generationId,
                'source_generation_id' => $requestData->sourceGenerationId,
                'character_count' => $resultData->characterCount,
                'estimated_cost_usd' => $resultData->estimatedCostUsd,
            ], fn (mixed $value): bool => $value !== null),
        ]);
    }
}
