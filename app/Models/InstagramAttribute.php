<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A custom contact-attribute definition (key + label), like WaDesk's
 * subscriber attributes. The built-ins name / email / phone are real columns
 * on instagram_contacts and are NOT stored here — this table holds only the
 * operator's own custom fields (e.g. city, order_id, plan).
 */
class InstagramAttribute extends Model
{
    protected $fillable = ['user_id', 'workspace_id', 'key', 'label'];

    /** The three built-in fields every contact always has. */
    public const BUILT_INS = [
        'name'  => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
    ];

    /** Normalise a free-typed label into a safe {{key}} slug. */
    public static function slug(string $raw): string
    {
        $s = Str::slug(trim($raw), '_');
        return $s !== '' ? mb_substr($s, 0, 64) : '';
    }

    /** Owner-scoped query (IgDesk = user_id; workspace_id stays 0). */
    public function scopeForOwner($q, ?int $userId, int $workspaceId = 0)
    {
        return $workspaceId > 0
            ? $q->where('workspace_id', $workspaceId)
            : $q->where('user_id', (int) $userId);
    }
}
