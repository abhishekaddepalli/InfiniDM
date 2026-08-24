<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line on an InstagramOrder.
 */
class InstagramOrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'retailer_id', 'name', 'qty',
        'unit_price', 'line_total', 'options',
    ];

    protected $casts = [
        'options'    => 'array',
        'qty'        => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(InstagramOrder::class, 'order_id');
    }
}
