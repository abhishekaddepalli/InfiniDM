<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Models\InstagramAccount;
use App\Models\InstagramAttribute;
use App\Models\InstagramContact;
use Illuminate\Http\Request;

/**
 * Contacts CRUD for IgDesk — the place to see everyone who DMed a connected
 * account and set their attribute VALUES by hand (name/email/phone + custom),
 * the manual counterpart to a flow's Ask node. Scoped by user_id: a contact
 * belongs to an instagram_account, and accounts belong to the signed-in user.
 */
class InstagramContactController extends Controller
{
    /** IDs of the accounts this user owns — the tenancy boundary for contacts. */
    private function accountIds(): array
    {
        return InstagramAccount::where('user_id', (int) auth()->id())->pluck('id')->all();
    }

    /** The user's custom attribute definitions (built-ins are handled separately). */
    private function customAttributes()
    {
        return InstagramAttribute::where('user_id', (int) auth()->id())->orderBy('label')->get();
    }

    /** Paginated, searchable contact list. */
    public function index(Request $request)
    {
        $ids = $this->accountIds();
        $q   = trim((string) $request->query('q', ''));

        $contacts = InstagramContact::whereIn('instagram_account_id', $ids ?: [0])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $query->where(function ($w) use ($like) {
                    $w->where('username', 'like', $like)
                      ->orWhere('name', 'like', $like)
                      ->orWhere('email', 'like', $like)
                      ->orWhere('phone', 'like', $like);
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('instagram.contacts.index', [
            'contacts' => $contacts,
            'q'        => $q,
        ]);
    }

    /** Contact detail + editable attribute values. */
    public function edit(int $id)
    {
        $contact = InstagramContact::whereIn('instagram_account_id', $this->accountIds() ?: [0])
            ->where('id', $id)->firstOrFail();

        return view('instagram.contacts.edit', [
            'contact'    => $contact,
            'custom'     => $this->customAttributes(),
            'builtIns'   => InstagramAttribute::BUILT_INS,
        ]);
    }

    /** Save the built-in fields + every custom attribute value onto the contact. */
    public function update(Request $request, int $id)
    {
        $contact = InstagramContact::whereIn('instagram_account_id', $this->accountIds() ?: [0])
            ->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'name'      => 'nullable|string|max:191',
            'email'     => 'nullable|email|max:191',
            'phone'     => 'nullable|string|max:32',
            'attr'      => 'nullable|array',
            'attr.*'    => 'nullable|string|max:500',
        ]);

        // Built-ins map straight to columns; blanks clear them.
        $contact->name  = trim((string) ($data['name'] ?? ''))  ?: null;
        $contact->email = trim((string) ($data['email'] ?? '')) ?: null;
        $contact->phone = trim((string) ($data['phone'] ?? '')) ?: null;

        // Custom values live in the JSON bag, keyed by the attribute slug. Only
        // keys that are real, owned attributes are accepted.
        $allowed = $this->customAttributes()->pluck('key')->all();
        $bag = is_array($contact->attributes) ? $contact->attributes : [];
        foreach ((array) ($data['attr'] ?? []) as $key => $val) {
            if (!in_array($key, $allowed, true)) continue;
            $val = trim((string) $val);
            if ($val === '') { unset($bag[$key]); } else { $bag[$key] = mb_substr($val, 0, 500); }
        }
        $contact->attributes = $bag;
        $contact->save();

        return redirect()->route('instagram.contacts.edit', $contact->id)
            ->with('status', 'Contact saved.');
    }
}
