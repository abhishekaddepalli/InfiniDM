<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A watermark (content-protection) config. One row per Instagram account, plus
 * an optional "All accounts" row (instagram_account_id = NULL) that applies to
 * every account the operator owns unless a per-account row overrides it.
 *
 * IgDesk is workspace-less — every row is scoped by user_id.
 */
class InstagramWatermark extends Model
{
    protected $fillable = [
        'user_id', 'instagram_account_id',
        'type', 'image_path', 'text', 'text_color',
        'position', 'size', 'opacity', 'is_active',
    ];

    protected $casts = [
        'instagram_account_id' => 'integer',
        'size'                 => 'integer',
        'opacity'              => 'integer',
        'is_active'            => 'boolean',
    ];

    /** The nine anchor codes, in grid order (top-left → bottom-right). */
    public const POSITIONS = ['tl', 'tc', 'tr', 'ml', 'mc', 'mr', 'bl', 'bc', 'br'];

    /**
     * Resolve the config that should watermark posts for $account:
     *   1. an ACTIVE per-account row, if one exists;
     *   2. otherwise the user's ACTIVE "All accounts" (NULL account) row;
     *   3. otherwise null (caller publishes the original, un-watermarked).
     *
     * Scoped by the account's owner so it works on the Node sweep path too,
     * where the account is loaded by id rather than from the session.
     */
    public static function forAccount(InstagramAccount $account): ?self
    {
        $uid = (int) $account->user_id;
        if ($uid <= 0) {
            return null;
        }

        $specific = static::where('user_id', $uid)
            ->where('instagram_account_id', $account->id)
            ->where('is_active', true)
            ->first();
        if ($specific) {
            return $specific;
        }

        return static::where('user_id', $uid)
            ->whereNull('instagram_account_id')
            ->where('is_active', true)
            ->first();
    }
}
