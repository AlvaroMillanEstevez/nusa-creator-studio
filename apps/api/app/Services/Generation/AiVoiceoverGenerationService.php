<?php

namespace App\Services\Generation;

use App\Data\Voiceover\AiVoiceoverRequestData;
use App\Jobs\GenerateAiVoiceoverJob;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use DomainException;
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
        if ($project->type !== 'ai_voiceover') {
            throw new DomainException('Only AI voiceover projects can generate voiceover plans.');
        }

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
