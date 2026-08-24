<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A marketing blog post. `status` is 'draft' or 'published'; `published_at` is
 * stamped the first time it goes live.
 */
class BlogPost extends Model
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'cover_image',
        'author', 'status', 'published_at', 'views',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];
}
