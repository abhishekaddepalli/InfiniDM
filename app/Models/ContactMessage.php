<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A message sent from the public Contact page. Read in admin → Front pages
 * (Contact tab shows the latest submissions).
 */
class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'subject', 'message', 'ip', 'is_read'];

    protected $casts = ['is_read' => 'boolean'];
}
