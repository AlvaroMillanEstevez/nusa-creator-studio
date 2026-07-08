<?php

namespace App\Exceptions;

use RuntimeException;

class MissingFashionTryOnAssetsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Please upload a model reference image and a garment reference image before generating a try-on.');
    }
}
