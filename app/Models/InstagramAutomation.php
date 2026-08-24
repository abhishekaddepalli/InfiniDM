<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstagramAutomation extends Model
{
    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'type', 'name',
        'trigger_keyword', 'match_mode', 'post_id',
        'public_reply', 'dm_message', 'flow_id',
        'is_active', 'fired_count', 'meta_json',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'meta_json'  => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(InstagramAccount::class, 'instagram_account_id');
    }

    /** Does this rule's keyword(s) match the given text? */
    public function matches(string $text): bool
    {
        $text = mb_strtolower(trim($text));
        if ($this->match_mode === 'any') return true;
        $kw = array_filter(array_map('trim', explode(',', mb_strtolower((string) $this->trigger_keyword))));
        foreach ($kw as $k) {
            if ($k === '') continue;
            if ($this->match_mode === 'exact' && $text === $k) return true;
            // Emoji / reaction keywords (🔥, 💜, …) have no letter boundaries —
            // the whole-word regex below would never fire, so match by contains.
            if ($this->match_mode !== 'exact' && !preg_match('/\p{L}/u', $k) && mb_strpos($text, $k) !== false) return true;
            // WHOLE-WORD, matching the WhatsApp keyword matcher. A plain
            // str_contains fired "hi" on "this" and "shop" on "shopping" —
            // the same substring bug already fixed on the WhatsApp side.
            if ($this->match_mode !== 'exact'
                && preg_match('/(?<!\p{L})' . preg_quote($k, '/') . '(?!\p{L})/u', $text)) return true;
        }
        return false;
    }

    /**
     * Pick one public comment reply at random from the primary + saved
     * variants (ManyChat parity — rotating replies avoid Instagram flagging
     * repeated identical comments as spam). Falls back to the single field.
     */
    public function pickPublicReply(): string
    {
        return $this->pickFrom($this->public_reply, 'public_reply_variants');
    }

    /** Pick one DM at random from the primary dm_message + saved variants. */
    public function pickDmMessage(): string
    {
        return $this->pickFrom($this->dm_message, 'dm_variants');
    }

    /** Random non-empty value from [primary, ...meta_json[$key]]. */
    private function pickFrom($primary, string $key): string
    {
        $pool = array_values(array_filter(
            array_merge([(string) $primary], array_map('strval', (array) data_get($this->meta_json, $key, []))),
            fn ($s) => trim($s) !== ''
        ));
        return $pool ? (string) $pool[array_rand($pool)] : (string) $primary;
    }
}
