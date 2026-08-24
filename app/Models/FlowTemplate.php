<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A curated, reusable flow customers can clone into their own automations.
 * `category` groups templates in the catalog; `flow_data` is the serialized
 * node/edge graph (JSON). `is_active` controls whether it appears to customers.
 */
class FlowTemplate extends Model
{
    protected $fillable = [
        'name', 'description', 'category', 'channel',
        'flow_data', 'is_active', 'sort',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'sort'      => 'int',
    ];

    public const CATEGORIES = [
        'general'  => 'General',
        'support'  => 'Support',
        'sales'    => 'Sales',
        'lead-gen' => 'Lead gen',
        'commerce' => 'Commerce',
        'welcome'  => 'Welcome',
    ];
}
