<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientCreditsException extends RuntimeException
{
    public function __construct(
        public readonly int $availableCredits,
        public readonly int $requiredCredits,
    ) {
        parent::__construct('You do not have enough platform credits to generate this campaign.');
    }
}
