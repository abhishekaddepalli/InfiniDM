<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order placed through an Instagram DM flow (Phase 3). Meta has no in-DM
 * checkout API, so payment goes out as a LINK and payment_status is driven by
 * the gateway webhook. status mirrors the native IG label funnel
 * (placed → paid → dispatched) so it maps cleanly onto the Sales Pipeline.
 */
class InstagramOrder extends Model
{
    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'igsid', 'order_ref', 'status',
        'payment_status', 'payment_provider', 'payment_ref', 'currency',
        'subtotal', 'total', 'customer_name', 'customer_phone',
        'shipping_address', 'meta', 'placed_at', 'paid_at', 'dispatched_at',
    ];

    protected $casts = [
        'meta'          => 'array',
        'subtotal'      => 'decimal:2',
        'total'         => 'decimal:2',
        'placed_at'     => 'datetime',
        'paid_at'       => 'datetime',
        'dispatched_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InstagramOrderItem::class, 'order_id');
    }

    public function scopeForWorkspace($q, int $wsId)
    {
        return $q->where('workspace_id', $wsId);
    }
}
