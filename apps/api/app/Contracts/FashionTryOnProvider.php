<?php

namespace App\Contracts;

interface FashionTryOnProvider
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array;
}
