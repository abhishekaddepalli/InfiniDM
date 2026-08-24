<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A saved, reusable Instagram DM template. Instagram has no Meta-approval
 * template system (unlike WABA) — these are operator snippets the inbox
 * composer inserts. `type` selects the payload shape:
 *   text          → body only
 *   quick_replies → body + items[] = [{title, payload}]
 *   buttons       → body + items[] = [{type: postback|web_url, title, value}]
 */
class InstagramTemplate extends Model
{
    protected $fillable = ['workspace_id', 'name', 'type', 'body', 'items'];

    protected $casts = ['items' => 'array'];

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
