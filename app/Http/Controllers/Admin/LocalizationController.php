<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Language;
use Illuminate\Http\Request;

/**
 * Currencies + Languages admin — the managed lists the General settings page
 * picks from, matching WaDesk's /admin/currencies and /admin/languages. Each is
 * an inline add-row + table with active/default toggles and delete.
 */
class LocalizationController extends Controller
{
    // ───────────────────────── Currencies ─────────────────────────

    public function currencies(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $currencies = Currency::query()
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")->orWhere('symbol', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('is_default')->orderBy('code')->paginate(20)->withQueryString();

        $defaultCode = Currency::where('is_default', true)->value('code') ?: 'USD';
        $allActive   = Currency::where('is_active', true)->orderBy('code')->get();
        $stats = [
            'total'    => Currency::count(),
            'active'   => Currency::where('is_active', true)->count(),
            'default'  => $defaultCode,
            'inactive' => Currency::where('is_active', false)->count(),
        ];

        return view('admin.currencies.index', compact('currencies', 'stats', 'allActive', 'defaultCode', 'q'));
    }

    public function storeCurrency(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'code'          => ['required', 'string', 'max:10', 'unique:currencies,code'],
            'symbol'        => ['nullable', 'string', 'max:20'],
            'precision'     => ['required', 'integer', 'min:0', 'max:6'],
            'exchange_rate' => ['required', 'numeric', 'min:0.000001'],
            'is_active'     => ['nullable', 'boolean'],
        ]);
        Currency::create([
            'name'          => $data['name'],
            'code'          => strtoupper($data['code']),
            'symbol'        => $data['symbol'] ?? null,
            'precision'     => $data['precision'],
            'exchange_rate' => $data['exchange_rate'],
            'is_active'     => (bool) ($data['is_active'] ?? false),
        ]);
        return back()->with('success', __('Currency added.'));
    }

    public function toggleCurrency(Currency $currency)
    {
        $currency->is_active = ! $currency->is_active;
        $currency->save();
        return back()->with('success', __('Currency updated.'));
    }

    public function setCurrencyDefault(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'exists:currencies,code']]);
        Currency::query()->update(['is_default' => false]);
        Currency::where('code', $data['code'])->update(['is_default' => true, 'is_active' => true]);
        \App\Models\Setting::set('default_currency', $data['code']);
        return back()->with('success', __('Default currency set to :code.', ['code' => $data['code']]));
    }

    public function destroyCurrency(Currency $currency)
    {
        if ($currency->is_default) {
            return back()->with('error', __('Cannot delete the default currency. Set another default first.'));
        }
        $currency->delete();
        return back()->with('success', __('Currency deleted.'));
    }

    /** Best-effort live rates from open.er-api.com (USD base). */
    public function fetchRates()
    {
        try {
            $res = \Illuminate\Support\Facades\Http::timeout(15)->get('https://open.er-api.com/v6/latest/USD');
            $rates = $res->json('rates', []);
            if (! $rates) {
                return back()->with('error', __('Could not fetch rates right now. Try again later.'));
            }
            $updated = 0;
            foreach (Currency::all() as $c) {
                if (isset($rates[$c->code])) {
                    $c->exchange_rate = (float) $rates[$c->code];
                    $c->save();
                    $updated++;
                }
            }
            return back()->with('success', __(':n rates updated from open.er-api.com.', ['n' => $updated]));
        } catch (\Throwable $e) {
            return back()->with('error', __('Rate fetch failed: :m', ['m' => $e->getMessage()]));
        }
    }

    // ───────────────────────── Languages ─────────────────────────

    public function languages()
    {
        $languages   = Language::orderBy('sort_order')->orderBy('name')->get();
        $defaultCode = Language::where('is_default', true)->value('code') ?: 'en';
        $stats = [
            'total'   => Language::count(),
            'active'  => Language::where('is_active', true)->count(),
            'default' => $defaultCode,
        ];
        return view('admin.languages.index', compact('languages', 'stats', 'defaultCode'));
    }

    public function toggleLanguage(Language $language)
    {
        $language->is_active = ! $language->is_active;
        $language->save();
        return back()->with('success', __('Language updated.'));
    }

    public function setLanguageDefault(Language $language)
    {
        Language::query()->update(['is_default' => false]);
        $language->is_default = true;
        $language->is_active = true;
        $language->save();
        \App\Models\Setting::set('default_language', $language->code);
        return back()->with('success', __('Default language set to :name.', ['name' => $language->name]));
    }

    public function destroyLanguage(Language $language)
    {
        if ($language->is_default) {
            return back()->with('error', __('Cannot delete the default language. Set another default first.'));
        }
        $language->delete();
        return back()->with('success', __('Language deleted.'));
    }
}
