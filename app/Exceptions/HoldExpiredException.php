<?php

declare(strict_types=1);

namespace App\Exceptions;

class HoldExpiredException extends \Exception
{
    protected $message = 'Время жизни холда истекло';
    protected $code = 409;
}
