<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Models\InstagramAccount;
use App\Models\InstagramWatermark;
use App\Models\UserFile;
use Illuminate\Http\Request;

/**
 * Watermark (content protection) — per-account overlay applied to images at
 * publish time. The operator picks an account (or "All accounts"), an image or
 * text watermark, a position on a 3x3 grid, and a size + opacity. When a
 * photo/carousel/story image is published, WatermarkService stamps a COPY of the
 * image and publishes the copy.
 *
 * Tenancy: IgDesk is workspace-less — every query and mutation is scoped to
 * auth()->id(). An account id in the request is only accepted after we confirm
 * the signed-in user owns it.
 */
class WatermarkController extends Controller
{
    private function ownerId(): int
    {
        return (int) auth()->id();
    }

    /** The active media disk — the same one the composer/Files write to. */
    private function disk(): string
    {
        return function_exists('media_disk') ? media_disk() : 'public';
    }

    /** Accounts the signed-in operator owns (the tenancy boundary). */
    private function accounts()
    {
        return InstagramAccount::where('user_id', $this->ownerId())
            ->orderBy('id')->get();
    }

    /** Settings page: account list + every saved config for this user. */
    public function index()
    {
        $uid      = $this->ownerId();
        $accounts = $this->accounts();

        // Key configs by account id; the "All accounts" row lands under 'all'.
        $configs = [];
        foreach (InstagramWatermark::where('user_id', $uid)->get() as $c) {
            $configs[$c->instagram_account_id === null ? 'all' : (int) $c->instagram_account_id] = $c;
        }

        return view('instagram.watermark.index', [
            'accounts'  => $accounts,
            'configs'   => $configs,
            'positions' => InstagramWatermark::POSITIONS,
        ]);
    }

    /** Create/update the config for one account (or "All accounts"). */
    public function save(Request $request)
    {
        $data = $request->validate([
            // 'all' or a numeric account id the user owns.
            'scope'         => 'required|string|max:16',
            'type'          => 'required|in:image,text',
            'image_file'    => 'nullable|file|mimes:png,jpg,jpeg,webp,gif|max:10240',
            'user_file_id'  => 'nullable|integer',
            'text'          => 'nullable|string|max:200',
            'text_color'    => 'nullable|string|max:16',
            'position'      => 'required|in:tl,tc,tr,ml,mc,mr,bl,bc,br',
            'size'          => 'required|integer|min:5|max:90',
            'opacity'       => 'required|integer|min:5|max:100',
            'is_active'     => 'nullable|boolean',
        ]);

        // Resolve + authorise the scope. 'all' → NULL account (every account).
        $accountId = null;
        if ($data['scope'] !== 'all') {
            $accountId = (int) $data['scope'];
            $owns = InstagramAccount::where('user_id', $this->ownerId())
                ->where('id', $accountId)->exists();
            if (!$owns) {
                return $this->fail($request, 'That account is not yours.');
            }
        }

        // Existing config for this scope (so we can keep its image on re-save).
        $config = InstagramWatermark::where('user_id', $this->ownerId())
            ->when($accountId === null,
                fn ($q) => $q->whereNull('instagram_account_id'),
                fn ($q) => $q->where('instagram_account_id', $accountId))
            ->first();

        $imagePath = $config?->image_path;

        if ($data['type'] === 'image') {
            // A freshly uploaded watermark image wins; otherwise a Files-library
            // pick; otherwise whatever the config already had.
            if ($request->hasFile('image_file')) {
                $f = $request->file('image_file');
                if ($f->isValid()) {
                    $stored = $f->store('instagram-media', $this->disk());
                    UserFile::record([
                        'user_id'       => $this->ownerId(),
                        'disk'          => $this->disk(),
                        'path'          => $stored,
                        'original_name' => $f->getClientOriginalName(),
                        'mime'          => $f->getMimeType(),
                        'size'          => $f->getSize(),
                        'folder'        => 'Watermarks',
                    ]);
                    $imagePath = $stored;
                }
            } elseif (!empty($data['user_file_id'])) {
                $file = UserFile::forUser($this->ownerId())
                    ->where('id', (int) $data['user_file_id'])->first();
                if ($file && str_starts_with((string) $file->mime, 'image')) {
                    $imagePath = $file->path;
                }
            }

            if (!$imagePath) {
                return $this->fail($request, 'Upload or pick a watermark image first.');
            }
        }

        if ($data['type'] === 'text' && trim((string) ($data['text'] ?? '')) === '') {
            return $this->fail($request, 'Enter the watermark text first.');
        }

        $payload = [
            'user_id'              => $this->ownerId(),
            'instagram_account_id' => $accountId,
            'type'                 => $data['type'],
            'image_path'           => $data['type'] === 'image' ? $imagePath : ($config->image_path ?? null),
            'text'                 => $data['type'] === 'text' ? trim((string) $data['text']) : ($config->text ?? null),
            'text_color'           => $data['text_color'] ?: '#ffffff',
            'position'             => $data['position'],
            'size'                 => (int) $data['size'],
            'opacity'              => (int) $data['opacity'],
            'is_active'            => $request->boolean('is_active', true),
        ];

        InstagramWatermark::updateOrCreate(
            ['user_id' => $this->ownerId(), 'instagram_account_id' => $accountId],
            $payload
        );

        $msg = 'Watermark saved.';
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $msg]);
        }
        return back()->with('status', $msg);
    }

    /** Toggle a config on/off without losing its settings. */
    public function toggle(Request $request, int $id)
    {
        $config = InstagramWatermark::where('user_id', $this->ownerId())->where('id', $id)->first();
        if (!$config) {
            return $this->fail($request, 'Watermark not found.', 404);
        }
        $config->is_active = !$config->is_active;
        $config->save();

        $msg = $config->is_active ? 'Watermark enabled.' : 'Watermark disabled.';
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'is_active' => $config->is_active, 'message' => $msg]);
        }
        return back()->with('status', $msg);
    }

    /** Delete a config. Owner-only. */
    public function destroy(Request $request, int $id)
    {
        $config = InstagramWatermark::where('user_id', $this->ownerId())->where('id', $id)->first();
        if (!$config) {
            return $this->fail($request, 'Watermark not found.', 404);
        }
        $config->delete();

        $msg = 'Watermark removed.';
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $msg]);
        }
        return back()->with('status', $msg);
    }

    /** Uniform error response for JSON + classic form posts. */
    private function fail(Request $request, string $message, int $code = 422)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], $code);
        }
        return back()->with('error', $message)->withInput();
    }
}
