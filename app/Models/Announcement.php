<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'text', 'tone', 'link_url', 'link_label', 'is_active', 'dismissible',
        'starts_at', 'expires_at', 'sort_order',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'dismissible' => 'boolean',
        'starts_at'   => 'datetime',
        'expires_at'  => 'datetime',
        'sort_order'  => 'integer',
    ];

    /** CSS colours for the tone pill/bar — matches WaDesk's toneClasses(). */
    public function toneClasses(): array
    {
        return match ($this->tone) {
            'promo'   => ['bg' => 'var(--color-wa-mint)', 'text' => 'var(--color-wa-deep)'],
            'warning' => ['bg' => 'rgba(229,160,78,0.16)', 'text' => '#B45309'],
            'success' => ['bg' => 'rgba(21,128,61,0.12)', 'text' => '#15803D'],
            default   => ['bg' => 'var(--color-paper-100)', 'text' => 'var(--color-ink-900)'],
        };
    }
}
