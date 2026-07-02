<?php

declare(strict_types=1);

namespace App\Exceptions;

class SlotNotAvailableException extends \Exception
{
    protected $message = 'Слот заполнен';
    protected $code = 409;
}
