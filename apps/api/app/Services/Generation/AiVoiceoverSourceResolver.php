<?php

namespace App\Services\Generation;

use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiVoiceoverSourceResolver
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function resolve(Project $project, User $user, array $options = []): array
    {
        $input = $project->input_payload ?? [];

        if (! is_array($input)) {
            $input = [];
        }

        $sourceGenerationId = $this->nullableInt($options['source_generation_id'] ?? $input['source_generation_id'] ?? null);
        $input = $this->applyLauncherOptions($input, $options, $sourceGenerationId !== null);

        $explicitSourceText = $this->nullableString($options['source_text'] ?? $input['source_text'] ?? null);
        $sourceGenerationExcerpt = $this->nullableString($input['source_generation_excerpt'] ?? null);

        if ($sourceGenerationId !== null) {
            $sourceGeneration = $user->generations()
                ->whereKey($sourceGenerationId)
                ->where('project_id', $project->getKey())
                ->first();

            if (! $sourceGeneration instanceof Generation) {
                throw ValidationException::withMessages([
                    'source_generation_id' => 'The selected source generation is unavailable.',
                ]);
            }

            $extractedText = $this->extractText($sourceGeneration->output_payload ?? []);

            if ($explicitSourceText === null) {
                if ($extractedText === null) {
                    throw ValidationException::withMessages([
                        'source_generation_id' => 'The selected source generation does not contain usable voiceover text.',
                    ]);
                }

                $explicitSourceText = $extractedText;
            }

            $sourceGenerationExcerpt = Str::limit($explicitSourceText, 240, '');
            $sourceLanguage = $this->sourceGenerationLanguage($sourceGeneration, $project, $input);
            $sourceWorkflow = $this->sourceWorkflow($sourceGeneration);
            $input['source_workflow'] = $sourceWorkflow;
            $input['source_origin_label'] = $this->sourceOriginLabel($sourceGeneration);
            $input['source_campaign_generation_id'] = $sourceWorkflow === 'product_content_kit' ? $sourceGeneration->getKey() : null;
            $input['source_analysis_generation_id'] = $sourceWorkflow === 'analyze_footage' ? $sourceGeneration->getKey() : null;
            $input['source_video_asset_id'] = $sourceWorkflow === 'analyze_footage' ? $this->sourceVideoAssetId($sourceGeneration) : null;
            $input['origin_workflow'] = 'voiceover';
            $input['source_generation_language'] = $sourceLanguage;
            $input['voiceover_output_language'] = $sourceLanguage;
            $input['language'] = $sourceLanguage;
            $input['script_preparation'] = [
                'mode' => 'saved_generation_language_locked',
                'translation_applied' => false,
                'original_text' => $explicitSourceText,
                'prepared_text' => $explicitSourceText,
            ];
        }

        $input['source_text'] = $explicitSourceText ?? $this->fallbackText($input);
        $input['source_generation_id'] = $sourceGenerationId;
        $input['source_generation_excerpt'] = $sourceGenerationExcerpt;

        if ($sourceGenerationId === null) {
            $language = $this->normalizeLanguage($this->nullableString($input['language'] ?? null)) ?? 'english';
            $input['language'] = $language;
            $input['source_generation_language'] = null;
            $input['voiceover_output_language'] = $language;
            $input['script_preparation'] = [
                'mode' => 'manual_text_spoken_as_written',
                'translation_applied' => false,
                'original_text' => $input['source_text'],
                'prepared_text' => $input['source_text'],
            ];
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function applyLauncherOptions(array $input, array $options, bool $hasSourceGeneration): array
    {
        $keys = $hasSourceGeneration
            ? ['tone', 'pace', 'voice_id', 'notes']
            : ['language', 'tone', 'pace', 'voice_id', 'notes'];

        foreach ($keys as $key) {
            $value = $this->nullableString($options[$key] ?? null);

            if ($value !== null) {
                $input[$key] = $value;
            }
        }

        $style = $this->nullableString($options['style'] ?? null);

        if ($style !== null) {
            $input['style'] = $style;
            $input['voice_style'] = $style;
        }

        if (array_key_exists('voice_settings', $options) && is_array($options['voice_settings'])) {
            $input['voice_settings'] = $this->voiceSettings($options['voice_settings']);
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function voiceSettings(array $settings): array
    {
        $allowed = [];

        foreach (['stability', 'similarity_boost', 'style'] as $key) {
            if (isset($settings[$key]) && is_numeric($settings[$key])) {
                $allowed[$key] = (float) $settings[$key];
            }
        }

        if (array_key_exists('use_speaker_boost', $settings)) {
            $allowed['use_speaker_boost'] = (bool) $settings['use_speaker_boost'];
        }

        return $allowed;
    }

    private function sourceWorkflow(Generation $generation): string
    {
        return match ($generation->job_type) {
            'generate_video_to_campaign' => 'analyze_footage',
            'generate_affiliate_campaign' => 'product_content_kit',
            'generate_motion_ai_video' => 'motion_ai_video',
            'generate_auto_video_edit' => 'auto_video_editor',
            default => 'unknown',
        };
    }

    private function sourceOriginLabel(Generation $generation): string
    {
        return match ($this->sourceWorkflow($generation)) {
            'analyze_footage' => 'Analyze Footage with AI · Video analysis',
            'motion_ai_video' => 'Motion AI Video · Generated footage',
            'auto_video_editor' => 'Auto Video Editor · Edited video',
            'product_content_kit' => 'Product Content Kit · Project brief',
            default => 'Saved generation',
        };
    }

    private function sourceVideoAssetId(Generation $generation): ?int
    {
        $value = data_get($generation->output_payload, 'source_video_asset_id')
            ?? data_get($generation->output_payload, 'provider_metadata.source_video_asset_id')
            ?? data_get($generation->input_payload, 'source_video_asset_id');

        return is_numeric($value) ? (int) $value : null;
    }

    private function sourceGenerationLanguage(Generation $generation, Project $project, array $input): string
    {
        foreach ([
            data_get($generation->input_payload, 'language'),
            data_get($generation->input_payload, 'voiceover_output_language'),
            data_get($generation->output_payload, 'language'),
            data_get($generation->output_payload, 'voiceover_output_language'),
            data_get($generation->output_payload, 'source_language'),
            data_get($project->input_payload, 'language'),
            $input['language'] ?? null,
        ] as $value) {
            $language = $this->normalizeLanguage($this->nullableString($value));

            if ($language !== null) {
                return $language;
            }
        }

        return 'english';
    }

    private function normalizeLanguage(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower(trim($value))) {
            'en', 'eng', 'english' => 'english',
            'id', 'indonesian', 'bahasa', 'bahasa indonesia' => 'indonesian',
            'es', 'spa', 'spanish', 'español', 'espanol' => 'spanish',
            default => null,
        };
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public function extractTextFromGeneration(Generation $generation): ?string
    {
        return $this->extractText($generation->output_payload ?? []);
    }

    /** @param array<string, mixed> $payload */
    private function extractText(array $payload): ?string
    {
        $direct = $this->nullableString($payload['voiceover_ready_script'] ?? null)
            ?? $this->nullableString(Arr::get($payload, 'main_script.voiceover_script'))
            ?? $this->nullableString($payload['narration_script'] ?? null)
            ?? $this->nullableString($payload['narration_voice_script'] ?? null)
            ?? $this->nullableString($payload['voiceover_script'] ?? null)
            ?? $this->nullableString(Arr::get($payload, 'audio_mix_plan.voiceover_script'))
            ?? $this->nullableString($payload['caption'] ?? null)
            ?? $this->nullableString($payload['posting_text'] ?? null)
            ?? $this->nullableString($payload['improved_caption'] ?? null);

        if ($direct !== null) {
            return $direct;
        }

        $campaignScenes = Arr::get($payload, 'script.scenes');
        if (is_array($campaignScenes)) {
            $lines = [];

            foreach ($campaignScenes as $scene) {
                if (is_array($scene) && is_string($scene['voiceover'] ?? null)) {
                    $lines[] = trim($scene['voiceover']);
                }
            }

            if ($lines !== []) {
                return implode("\n", array_filter($lines));
            }
        }

        $storyboard = $payload['storyboard'] ?? null;
        if (is_array($storyboard)) {
            $lines = [];

            foreach ($storyboard as $scene) {
                if (is_array($scene) && is_string($scene['voiceover'] ?? null)) {
                    $lines[] = trim($scene['voiceover']);
                }
            }

            if ($lines !== []) {
                return implode("\n", array_filter($lines));
            }
        }

        return null;
    }

    /** @param array<string, mixed> $input */
    private function fallbackText(array $input): string
    {
        $product = $this->nullableString($input['product_name'] ?? null) ?? 'this product';
        $description = $this->nullableString($input['product_description'] ?? null)
            ?? 'a practical product for short-form content';
        $audience = $this->nullableString($input['target_audience'] ?? null) ?? 'social commerce viewers';

        return "Meet {$product}. It is described as {$description}. Here is why it may be useful for {$audience}: check the product details, compare it with your needs, and decide if it fits your routine.";
    }
}
