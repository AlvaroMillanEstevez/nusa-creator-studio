<?php

namespace App\Services\Generation;

use App\Contracts\FashionTryOnProvider;
use App\Models\User;
use App\Support\FashionTryOnCapabilities;

class FashionTryOnProviderManager implements FashionTryOnProvider
{
    public function __construct(
        private readonly MockFashionTryOnProvider $mock,
        private readonly FashnFashionTryOnProvider $fashn,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        return $this->mock->generate($input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generateForUser(array $input, User $user): array
    {
        if ($this->selectedProviderForUser($user) === 'fashn') {
            return $this->fashn->generateForUser($input, $user);
        }

        return $this->mock->generate($input);
    }

    public function selectedProviderForUser(User $user): string
    {
        return FashionTryOnCapabilities::selectedProviderForUser($user);
    }
}
