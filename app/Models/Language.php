<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = ['code', 'name', 'native_name', 'is_rtl', 'is_active', 'is_default', 'sort_order'];

    protected $casts = [
        'is_rtl'     => 'boolean',
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** WaDesk's languages page reads $l->direction (ltr | rtl). */
    public function getDirectionAttribute(): string
    {
        return $this->is_rtl ? 'rtl' : 'ltr';
    }
}
