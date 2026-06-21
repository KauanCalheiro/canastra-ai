<?php

namespace App\Exceptions;

use Illuminate\Support\Str;

abstract class DomainException extends \Exception
{
    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return Str::snake(Str::beforeLast(class_basename($this), 'Exception'));
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [];
    }
}
