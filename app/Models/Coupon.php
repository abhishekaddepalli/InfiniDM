<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A discount code applied against a subscription package. `type` decides how
 * `value` is read — 'percent' (0-100) or 'fixed' (flat amount off).
 */
class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value',
        'max_redemptions', 'redeemed_count',
        'starts_at', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'value'      => 'decimal:2',
        'is_active'  => 'boolean',
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];
}
