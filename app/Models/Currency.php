<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = ['code', 'name', 'symbol', 'exchange_rate', 'precision', 'is_active', 'is_default'];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'precision'     => 'integer',
        'is_active'     => 'boolean',
        'is_default'    => 'boolean',
    ];
}
