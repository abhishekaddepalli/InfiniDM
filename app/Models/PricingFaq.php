<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One FAQ row on /instagram/plans. Admin manages it at /admin/pricing-faqs.
 * The plans view fetches active rows ordered by sort_order; if the table is
 * empty it falls back to a baked-in default set.
 */
class PricingFaq extends Model
{
    protected $table = 'pricing_faqs';

    protected $fillable = ['question', 'answer', 'sort_order', 'is_active', 'placement'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
