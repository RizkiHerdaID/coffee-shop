<?php

namespace App\Exceptions;

use RuntimeException;

class MissingAiKeyException extends RuntimeException
{
    public static function create(): static
    {
        return new static('DeepSeek API key is not configured. Set DEEPSEEK_API_KEY in your .env file.');
    }
}
