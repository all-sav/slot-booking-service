<?php

declare(strict_types=1);

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $capacity
 * @property int $remaining
 * @method static \Database\Factories\SlotFactory factory($count = null, $state = [])
 * @mixin Eloquent
 */
class Slot extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function hasAvailability(): bool
    {
        return $this->remaining > 0;
    }
}
