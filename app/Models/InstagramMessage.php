<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramMessage extends Model
{
    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'igsid',
        'direction', 'body', 'attachment_type', 'attachment_url', 'mid', 'source', 'reaction', 'pinned_at', 'meta', 'sent_at',
    ];

    protected $casts = [
        'pinned_at' => 'datetime',
        'sent_at'   => 'datetime',
        'meta'      => 'array',
    ];

    /**
     * Normalise Instagram's time for a message. The inbox backfills over the
     * Graph API, so rows arrive in the API's order — insert order (id /
     * created_at) is NOT message order, and the thread list must rank on this.
     * Meta ships a millisecond epoch on webhook messaging entries and an ISO8601
     * `created_time` on the conversation read-back; anything unusable (an
     * outbound we just sent, a payload without a time) means "right now".
     */
    public static function igTime($raw): \Illuminate\Support\Carbon
    {
        if (is_numeric($raw) && (int) $raw > 0) {
            $n = (int) $raw;
            // Messaging entries are milliseconds; tolerate a seconds epoch.
            return $n > 99999999999
                ? \Illuminate\Support\Carbon::createFromTimestampMs($n)
                : \Illuminate\Support\Carbon::createFromTimestamp($n);
        }
        if (is_string($raw) && trim($raw) !== '') {
            try { return \Illuminate\Support\Carbon::parse($raw); } catch (\Throwable $e) {}
        }
        return \Illuminate\Support\Carbon::now();
    }

    /**
     * Append a row and return it. $sentAt takes Meta's raw time (ms epoch or
     * ISO8601) — see igTime(); omit it for messages we are sending right now.
     */
    public static function log(InstagramAccount $account, string $igsid, string $direction, ?string $body, ?string $source = null, ?string $mid = null, ?string $attachmentType = null, ?string $attachmentUrl = null, ?array $meta = null, $sentAt = null): self
    {
        $row = self::create([
            'workspace_id'         => $account->workspace_id,
            'instagram_account_id' => $account->id,
            'igsid'                => $igsid,
            'direction'            => $direction,
            'body'                 => $body,
            'attachment_type'      => $attachmentType,
            'attachment_url'       => $attachmentUrl,
            'source'               => $source,
            'mid'                  => $mid,
            'meta'                 => $meta,
            // Instagram's time, not ours — a backfilled row is inserted now but
            // may have been sent days ago, and the thread list sorts on this.
            'sent_at'              => self::igTime($sentAt),
        ]);
        // Track every add so the whole inbox lifecycle (added → wiped) is
        // visible in storage/logs/laravel.log alongside [IG-WIPE].
        \Illuminate\Support\Facades\Log::info('[IG-MSG] added', [
            'id'      => $row->id,
            'account' => $account->id,
            'igsid'   => $igsid,
            'dir'     => $direction,
            'source'  => $source,
            'len'     => strlen((string) $body),
        ]);

        // Mirror IgDesk-ORIGINATED outbound sends (IG inbox, flows, auto-reply)
        // to a connected WaDesk unified inbox. Skip source='wadesk' — that reply
        // already came FROM WaDesk over the bridge, so pushing it back double-logs.
        if ($direction === 'out' && $source !== 'wadesk') {
            try {
                // Carry the template's interactive buttons through the mirror so the
                // WaDesk unified inbox renders the SAME button card, not plain text.
                $mirrorButtons = (is_array($meta) && ! empty($meta['buttons']) && is_array($meta['buttons']))
                    ? array_values($meta['buttons']) : null;

                // Instagram's own message ECHO (a from_me webhook) arrives with NO
                // button data — only text. So when THIS row has none, recover the
                // buttons from the send-time row we already logged for the same
                // provider message id (which DID carry the interactive meta). This
                // makes the button card render in WaDesk no matter which path (native
                // send, bridge send, or Meta's button-less echo) reached the inbox.
                if (! $mirrorButtons && $mid) {
                    foreach (self::where('instagram_account_id', $account->id)
                        ->where('mid', $mid)->whereNotNull('meta')
                        ->orderByDesc('id')->limit(6)->get() as $sib) {
                        $sm = is_array($sib->meta) ? $sib->meta : [];
                        if (! empty($sm['buttons']) && is_array($sm['buttons'])) {
                            $mirrorButtons = array_values($sm['buttons']);
                            break;
                        }
                    }
                }
                \App\Services\Instagram\WadeskPushService::pushOutbound(
                    $account, $igsid, $attachmentType ?: 'text', $body, $attachmentUrl, $mid, $mirrorButtons
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::info('[WADESK-PUSH] outbound mirror failed: ' . $e->getMessage());
            }
        }

        return $row;
    }
}
