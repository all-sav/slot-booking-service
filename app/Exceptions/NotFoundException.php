<?php

declare(strict_types=1);

namespace App\Exceptions;

class NotFoundException extends \Exception
{
    public static function notFound(string $model): self
    {
        return new self('Model ' . $model . ' not found', 404);
    }
}
