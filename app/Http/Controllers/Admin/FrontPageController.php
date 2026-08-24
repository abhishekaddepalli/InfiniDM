<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Support\FrontContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Admin → Front pages. One grouped form that edits every line of the public
 * marketing site (home, about, contact, legal + header/footer) plus the brand
 * nav labels. Values persist to the settings store as front_<key>, read back on
 * the public pages through front(). No live editor — just plain forms.
 */
class FrontPageController extends Controller
{
    /** The editor: all groups + current values, plus recent contact messages. */
    public function index()
    {
        $groups  = FrontContent::groups();
        $fields  = FrontContent::fields();
        $values  = [];
        foreach (FrontContent::flat() as $key => $meta) {
            $values[$key] = FrontContent::get($key);
        }

        $messages = [];
        $unread   = 0;
        if (Schema::hasTable('contact_messages')) {
            $messages = ContactMessage::latest()->limit(30)->get();
            $unread   = ContactMessage::where('is_read', false)->count();
        }

        return view('admin.front.index', compact('groups', 'fields', 'values', 'messages', 'unread'));
    }

    /** Persist every registry field back to the settings store. */
    public function save(Request $request)
    {
        // Fields post as fc[<key>] (array notation) so keys containing dots or
        // dashes — "about.hero.headline", "cta-final.headline" — survive PHP's
        // form-name mangling. Read the array directly by literal key.
        $fc = (array) $request->input('fc', []);

        foreach (FrontContent::flat() as $key => $meta) {
            $type = $meta[1] ?? 'text';

            if ($type === 'bool') {
                Setting::set('front_' . $key, ! empty($fc[$key]) ? '1' : '0', 'bool');
            } else {
                Setting::set('front_' . $key, (string) ($fc[$key] ?? ''));
            }
        }

        // Stamp the legal "last updated" date so the public legal pages reflect
        // the edit.
        Setting::set('legal_updated_at', now()->toDateTimeString());

        return back()->with('success', __('Front pages saved.'));
    }

    /** Mark a contact message read (small inline action on the Contact tab). */
    public function markRead(ContactMessage $message)
    {
        $message->update(['is_read' => true]);
        return back()->with('success', __('Marked as read.'));
    }
}
