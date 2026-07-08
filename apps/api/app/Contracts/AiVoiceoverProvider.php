<?php

namespace App\Contracts;

use App\Data\Voiceover\AiVoiceoverRequestData;
use App\Data\Voiceover\AiVoiceoverResultData;

interface AiVoiceoverProvider
{
    public function generate(AiVoiceoverRequestData $data): AiVoiceoverResultData;
}
