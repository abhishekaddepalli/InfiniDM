<?php

namespace App\Services\Instagram;

use App\Models\InstagramAccount;
use App\Models\InstagramContact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IgDesk → WaDesk push.
 *
 * When a new Instagram DM arrives, this ships it to the connected WaDesk install
 * so it surfaces in WaDesk's UNIFIED team-inbox alongside WhatsApp. It lands on
 * WaDesk's POST /api/instaflow/inbound, authenticated by the shared secret.
 *
 * Best-effort by design: any failure is logged and swallowed so a WaDesk that is
 * down / unreachable NEVER breaks Instagram's own webhook processing.
 */
class WadeskPushService
{
    public static function pushInbound(
        InstagramAccount $account,
        string $igsid,
        ?string $type,
        ?string $text,
        ?string $mediaUrl = null,
        ?string $mid = null
    ): void {
        self::push($account, $igsid, $type, $text, $mediaUrl, $mid, false);
    }

    /**
     * Mirror an IgDesk-ORIGINATED send (IG inbox reply, flow, auto-reply) to
     * WaDesk so its unified inbox shows both sides in real time. NOT called for
     * WaDesk-originated replies (source='wadesk') — those already came from WaDesk
     * over the bridge, so re-pushing would double-log. Guarded in InstagramMessage::log.
     */
    public static function pushOutbound(
        InstagramAccount $account,
        string $igsid,
        ?string $type,
        ?string $text,
        ?string $mediaUrl = null,
        ?string $mid = null,
        ?array $buttons = null
    ): void {
        self::push($account, $igsid, $type, $text, $mediaUrl, $mid, true, $buttons);
    }

    private static function push(
        InstagramAccount $account,
        string $igsid,
        ?string $type,
        ?string $text,
        ?string $mediaUrl,
        ?string $mid,
        bool $fromMe,
        ?array $buttons = null
    ): void {
        if (! WadeskLink::isConfigured() || ! WadeskLink::pushEnabled()) {
            return;
        }

        try {
            $c      = InstagramContact::where('instagram_account_id', $account->id)->where('igsid', $igsid)->first();
            $handle = $c?->username ?: $igsid;

            $payload = [
                'event'        => 'message',
                'workspace_id' => WadeskLink::workspaceId(),
                'account'      => [
                    'id'       => (string) $account->id,
                    'username' => (string) $account->username,
                    'name'     => (string) ($account->name ?: $account->username),
                    // Self-healing proxy url (IG avatar URLs expire) — see
                    // InstagramController::igAvatar().
                    'avatar'   => $account->profile_pic_url ? url('ig-avatar/' . $account->id . '/self') : '',
                ],
                'conversation' => [
                    'id'     => $account->id . '_' . $igsid,
                    'name'   => (string) ($c?->name ?: ('@' . $handle)),
                    'handle' => (string) $handle,
                    'avatar' => (string) ($c?->avatar_proxy_url ?: ''),
                ],
                'message'      => [
                    'id'        => (string) ($mid ?: ''),
                    'from_me'   => $fromMe,
                    'type'      => $type ?: 'text',
                    'text'      => (string) $text,
                    'media_url' => (string) $mediaUrl,
                    // Interactive buttons so WaDesk renders the SAME card, not text.
                    'buttons'   => (is_array($buttons) && $buttons) ? array_values($buttons) : null,
                ],
            ];

            $target = WadeskLink::wadeskUrl() . '/api/instaflow/inbound';
            Log::info('[WADESK-PUSH] →', [
                'target'    => $target,
                'ws'        => WadeskLink::workspaceId(),
                'from_me'   => $fromMe,
                'conv'      => $payload['conversation']['id'],
                'mid'       => $mid,
                // A private LAN host here (10.x / 192.168.x / 127.x) can't be
                // reached from a hosted IgDesk — WaDesk must PULL instead.
                'lan_host'  => (bool) preg_match('#//(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)#', $target),
            ]);
            $r = Http::withHeaders(['X-Instaflow-Secret' => WadeskLink::secret()])
                ->acceptJson()
                ->timeout(8)
                ->post($target, $payload);

            Log::info('[WADESK-PUSH] ←', ['status' => $r->status(), 'ok' => $r->successful(), 'body' => mb_substr($r->body(), 0, 200)]);
        } catch (\Throwable $e) {
            Log::warning('[WADESK-PUSH] failed (WaDesk unreachable? use PULL): ' . $e->getMessage());
        }
    }
}
