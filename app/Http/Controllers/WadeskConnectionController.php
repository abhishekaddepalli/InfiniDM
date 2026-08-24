<?php

namespace App\Http\Controllers;

use App\Services\Instagram\WadeskLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * The "Connect WaDesk" operator page.
 *
 * The operator generates a shared secret here, points this IgDesk at their
 * WaDesk install (URL + target workspace), and pastes this IgDesk's URL +
 * the generated secret into WaDesk's Add-ons → Connect Instagram card. From
 * then on, IG DMs push into WaDesk's unified inbox and replies come back through
 * WadeskBridgeController.
 *
 * Works in BOTH builds: gated to admin where the host provides an admin surface
 * (WaDesk addon), and to the sole authenticated operator in standalone IgDesk.
 */
class WadeskConnectionController extends Controller
{
    public function index(): View
    {
        $link = WadeskLink::all();

        return view('instagram.wadesk-connection', [
            'wadeskUrl'    => $link['wadesk_url'],
            'secret'       => $link['secret'],
            'workspaceId'  => $link['workspace_id'],
            'pushEnabled'  => $link['push_enabled'],
            'isConfigured' => WadeskLink::isConfigured(),
            // The base URL the operator pastes into WaDesk (this IgDesk's own).
            'instaflowUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    /** Save the WaDesk target (URL + workspace + push toggle). Auto-mints a secret if none yet. */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'wadesk_url'   => 'nullable|url',
            'workspace_id' => 'nullable|integer|min:1',
            'push_enabled' => 'nullable|boolean',
        ]);

        $save = [
            'wadesk_url'   => (string) ($data['wadesk_url'] ?? ''),
            'workspace_id' => (int) ($data['workspace_id'] ?? 1),
            'push_enabled' => $request->boolean('push_enabled'),
        ];

        // First save with no secret yet → mint one so the link is usable
        // immediately (the operator still has to paste it into WaDesk).
        if (WadeskLink::secret() === '') {
            $save['secret'] = WadeskLink::generateSecret();
        }

        if (! WadeskLink::save($save)) {
            return back()->with('error', __('Could not write the connection file. Check storage/app is writable.'));
        }

        return back()->with('status', __('WaDesk connection saved.'));
    }

    /** Rotate the shared secret. Invalidates the old one — WaDesk must be re-pasted. */
    public function generate(): RedirectResponse
    {
        if (! WadeskLink::save(['secret' => WadeskLink::generateSecret()])) {
            return back()->with('error', __('Could not write the connection file. Check storage/app is writable.'));
        }

        return back()->with('status', __('New secret generated. Copy it into WaDesk → Add-ons → Connect Instagram.'));
    }

    /**
     * Reachability check — POST a harmless status event to WaDesk. WaDesk's
     * inbound acknowledges non-message events WITHOUT writing a row, so this
     * proves URL + secret + workspace all line up without polluting the inbox.
     */
    public function test(): RedirectResponse
    {
        if (! WadeskLink::isConfigured()) {
            return back()->with('warning', __('Set the WaDesk URL and generate a secret first.'));
        }

        try {
            $r = Http::withHeaders(['X-Instaflow-Secret' => WadeskLink::secret()])
                ->acceptJson()
                ->timeout(10)
                ->post(WadeskLink::wadeskUrl() . '/api/instaflow/inbound', [
                    'event'        => 'status',
                    'workspace_id' => WadeskLink::workspaceId(),
                ]);

            if ($r->successful() && ($r->json('ok') === true)) {
                return back()->with('status', __('Connected — WaDesk accepted the test with this secret.'));
            }
            if ($r->status() === 401) {
                return back()->with('error', __('WaDesk rejected the secret (401). Re-paste it in WaDesk and try again.'));
            }
            if ($r->status() === 422) {
                return back()->with('error', __('WaDesk does not recognise workspace #:id.', ['id' => WadeskLink::workspaceId()]));
            }
            return back()->with('error', __('WaDesk returned HTTP :s.', ['s' => $r->status()]));
        } catch (\Throwable $e) {
            return back()->with('error', __('Could not reach WaDesk: :m', ['m' => $e->getMessage()]));
        }
    }
}
