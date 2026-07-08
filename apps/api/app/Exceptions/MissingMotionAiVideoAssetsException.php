<?php

namespace App\Exceptions;

use RuntimeException;

class MissingMotionAiVideoAssetsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Please upload at least one product image, source video, background reference or brand reference before generating a motion video.');
    }
}
