<?php

namespace App\Http\Controllers;

use App\Support\LocaleSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Backs the header language dropdown. Stores the pick in the session (so it
 * applies immediately, and works for guests), and — when signed in — persists
 * it to users.locale so it survives a new session. SetLocale reads both.
 */
class LocaleController extends Controller
{
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $code = trim((string) $request->input('code', ''));

        if ($code === '' || ! LocaleSettings::isAvailable($code)) {
            $msg = __('That language is not available.');
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => $msg], 422)
                : back()->with('error', $msg);
        }

        $request->session()->put('app_locale', $code);

        if ($user = Auth::user()) {
            try {
                $user->forceFill(['locale' => $code])->save();
            } catch (\Throwable $e) {
                // Column may be missing on a stale schema — the session pick still applies.
            }
        }

        app()->setLocale($code);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'        => true,
                'locale'    => $code,
                'direction' => LocaleSettings::directionFor($code),
            ]);
        }

        return back()->with('status', __('Language updated.'));
    }
}
