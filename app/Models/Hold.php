<?php

namespace App\Models;

use App\Dictionary\HoldStatusEnum;
use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $slot_id
 * @property string $idempotency_key
 * @property HoldStatusEnum $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @mixin Eloquent
 */
class Hold extends Model
{
    private const string EXPIRE_TTL = '5M';

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'slot_id',
        'idempotency_key',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => HoldStatusEnum::class,
        ];
    }

    public function isExpired(): bool
    {
        return now()->gt($this->created_at->add(self::EXPIRE_TTL));
    }
}
