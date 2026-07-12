<?php

namespace App\Http\Resources;

use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MediaAsset
 */
class MediaAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $origin = $this->origin();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'project_id' => $this->project_id,
            'type' => $this->type,
            'usage_role' => $this->usage_role,
            'origin' => $origin,
            'origin_label' => ucfirst($origin),
            'disk' => $this->disk,
            'storage_identifier' => 'media-'.$this->id,
            'stream_url' => $this->isPreviewableMedia()
                ? route('media-assets.stream', ['mediaAsset' => $this->id], false)
                : null,
            'download_url' => $this->isPreviewableMedia()
                ? route('media-assets.download', ['mediaAsset' => $this->id], false)
                : null,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'size_label' => $this->sizeLabel(),
            'metadata' => $this->metadata,
            'project_label' => $this->whenLoaded('project', fn (): ?string => $this->project?->title),
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project === null ? null : [
                'id' => $this->project->id,
                'title' => $this->project->title,
                'type' => $this->project->type,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function isPreviewableMedia(): bool
    {
        return in_array($this->type, ['image', 'audio', 'video'], true);
    }

    private function origin(): string
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        if (($metadata['generated'] ?? false) === true) {
            return 'generated';
        }

        if (isset($metadata['generation_id']) || isset($metadata['provider']) || isset($metadata['render_provider'])) {
            return 'generated';
        }

        if (in_array($this->usage_role, ['voiceover_audio', 'edited_video_result'], true)) {
            return 'generated';
        }

        if (str_contains($this->path, '/generated/')) {
            return 'generated';
        }

        return 'uploaded';
    }

    private function sizeLabel(): ?string
    {
        if ($this->size_bytes === null) {
            return null;
        }

        if ($this->size_bytes < 1024) {
            return $this->size_bytes.' B';
        }

        if ($this->size_bytes < 1024 * 1024) {
            return number_format($this->size_bytes / 1024, 1).' KB';
        }

        return number_format($this->size_bytes / (1024 * 1024), 1).' MB';
    }
}
