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
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'project_id' => $this->project_id,
            'type' => $this->type,
            'usage_role' => $this->usage_role,
            'disk' => $this->disk,
            'storage_identifier' => 'media-'.$this->id,
            'stream_url' => $this->isPlayableMedia()
                ? route('media-assets.stream', ['mediaAsset' => $this->id], false)
                : null,
            'download_url' => $this->isPlayableMedia()
                ? route('media-assets.download', ['mediaAsset' => $this->id], false)
                : null,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'size_label' => $this->sizeLabel(),
            'metadata' => $this->metadata,
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project === null ? null : [
                'id' => $this->project->id,
                'title' => $this->project->title,
                'type' => $this->project->type,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function isPlayableMedia(): bool
    {
        return in_array($this->type, ['audio', 'video'], true);
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
