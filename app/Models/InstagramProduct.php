<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A product cached from the merchant's Commerce Manager catalog (via the
 * Catalog API). Feeds the DM product carousel / product template — the
 * messaging primitives already exist in InstagramService; this is the data.
 */
class InstagramProduct extends Model
{
    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'catalog_id', 'retailer_id',
        'product_id', 'name', 'description', 'image_url', 'currency', 'price',
        'availability', 'url', 'raw', 'synced_at',
    ];

    protected $casts = [
        'raw'       => 'array',
        'price'     => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    public function scopeForWorkspace($q, int $wsId)
    {
        return $q->where('workspace_id', $wsId);
    }
}
