<?php

namespace App\Services\Generation;

use App\Data\Voiceover\AiVoiceoverRequestData;
use App\Jobs\GenerateAiVoiceoverJob;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use Illuminate\Validation\ValidationException;

class AiVoiceoverGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
        private readonly AiVoiceoverSourceResolver $sourceResolver,
        private readonly AiVoiceoverProviderManager $providers,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(Project $project, User $user, array $options = []): Generation
    {
        $this->ensureProjectHasVoiceoverSource($project, $options);

        $inputPayload = $this->sourceResolver->resolve($project, $user, $options);
        $previewData = AiVoiceoverRequestData::fromGeneration(
            generation: new Generation([
                'project_id' => $project->getKey(),
                'input_payload' => $inputPayload,
            ]),
            user: $user,
            input: $inputPayload,
        );
        $this->ensureWithinCharacterLimit($previewData->text);

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_ai_voiceover',
            creditCost: $this->tools->creditCost('ai_voiceover'),
            creditReason: 'AI voiceover generation',
            dispatch: fn (int $generationId) => GenerateAiVoiceoverJob::dispatch($generationId),
            inputPayload: $inputPayload,
            provider: $this->providers->selectedProviderForUser($previewData->realProviderAllowed),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function ensureProjectHasVoiceoverSource(Project $project, array $options): void
    {
        if ($project->type === 'ai_voiceover') {
            return;
        }

        $hasSourceText = is_string($options['source_text'] ?? null) && trim($options['source_text']) !== '';
        $hasSourceGeneration = isset($options['source_generation_id']) && $options['source_generation_id'] !== null && $options['source_generation_id'] !== '';

        if ($hasSourceText || $hasSourceGeneration) {
            return;
        }

        throw ValidationException::withMessages([
            'source_text' => 'Provide source text or choose a source generation for this project voiceover.',
        ]);
    }

    private function ensureWithinCharacterLimit(string $text): void
    {
        $limit = max(1, (int) config('voiceover.max_characters', 1000));

        if (mb_strlen($text) <= $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'source_text' => "Voiceover source text may not be greater than {$limit} characters.",
        ]);
    }
}
