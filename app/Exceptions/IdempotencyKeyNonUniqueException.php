<?php

declare(strict_types=1);

namespace App\Exceptions;

class IdempotencyKeyNonUniqueException extends \Exception
{
    protected $message = 'Ошибка уникальности ключа идемпотентности';
    protected $code = 409;
}
