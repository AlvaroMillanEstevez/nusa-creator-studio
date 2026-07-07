<?php

namespace App\Contracts;

interface MotionAiVideoProvider
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array;
}