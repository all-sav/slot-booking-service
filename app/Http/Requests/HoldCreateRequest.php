<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HoldCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->hasHeader('Idempotency-Key');
    }
}
