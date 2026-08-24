<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Admin panes for SEO meta, analytics/script injection and privacy/consent.
 *
 * Every field is a single App\Models\Setting row — no dedicated tables. Reads
 * go through the cached Setting::get(); writes through Setting::set(), which
 * stamps a type ('bool'/'int'/'string') so the value casts back correctly.
 */
class SeoAnalyticsController extends Controller
{
    /* ---------------------------------------------------------------- SEO */

    private const SEO_KEYS = [
        'seo_title', 'seo_description', 'seo_keywords', 'seo_og_image',
        'seo_twitter_handle', 'seo_canonical_url', 'seo_robots',
    ];

    public function seo()
    {
        $defaults = ['seo_robots' => 'index,follow'];
        $values = [];
        foreach (self::SEO_KEYS as $k) {
            $values[$k] = Setting::get($k, $defaults[$k] ?? '');
        }

        return view('admin.settings.seo', compact('values'));
    }

    public function seoSave(Request $r)
    {
        $r->validate([
            'seo_title'          => 'nullable|string|max:180',
            'seo_description'    => 'nullable|string|max:400',
            'seo_keywords'       => 'nullable|string|max:400',
            'seo_og_image'       => 'nullable|string|max:300',
            'seo_twitter_handle' => 'nullable|string|max:60',
            'seo_canonical_url'  => 'nullable|string|max:300',
            'seo_robots'         => 'nullable|string|max:60',
        ]);

        foreach (self::SEO_KEYS as $k) {
            Setting::set($k, $r->input($k, ''));
        }

        return back()->with('success', __('SEO settings saved.'));
    }

    /* ---------------------------------------------------------- Analytics */

    private const ANALYTICS_STRING_KEYS = [
        'analytics_ga4_id', 'analytics_gtm_id', 'analytics_meta_pixel_id',
        'analytics_clarity_id', 'analytics_head_scripts', 'analytics_body_scripts',
    ];

    public function analytics()
    {
        $values = [];
        foreach (self::ANALYTICS_STRING_KEYS as $k) {
            $values[$k] = Setting::get($k, '');
        }
        $values['analytics_enabled'] = Setting::get('analytics_enabled', true);

        return view('admin.settings.analytics', compact('values'));
    }

    public function analyticsSave(Request $r)
    {
        $r->validate([
            'analytics_ga4_id'        => 'nullable|string|max:60',
            'analytics_gtm_id'        => 'nullable|string|max:60',
            'analytics_meta_pixel_id' => 'nullable|string|max:60',
            'analytics_clarity_id'    => 'nullable|string|max:60',
            'analytics_head_scripts'  => 'nullable|string|max:8000',
            'analytics_body_scripts'  => 'nullable|string|max:8000',
            'analytics_enabled'       => 'nullable|boolean',
        ]);

        foreach (self::ANALYTICS_STRING_KEYS as $k) {
            Setting::set($k, $r->input($k, ''));
        }
        Setting::set('analytics_enabled', (bool) $r->boolean('analytics_enabled'), 'bool');

        return back()->with('success', __('Analytics settings saved.'));
    }

    /* ------------------------------------------------------------ Privacy */

    public function privacy()
    {
        $values = [
            'privacy_policy_url'      => Setting::get('privacy_policy_url', ''),
            'terms_url'               => Setting::get('terms_url', ''),
            'cookie_consent_enabled'  => Setting::get('cookie_consent_enabled', false),
            'cookie_consent_text'     => Setting::get('cookie_consent_text', ''),
            'gdpr_enabled'            => Setting::get('gdpr_enabled', false),
            'data_retention_days'     => Setting::get('data_retention_days', 365),
            'privacy_contact_email'   => Setting::get('privacy_contact_email', ''),
        ];

        return view('admin.settings.privacy', compact('values'));
    }

    public function privacySave(Request $r)
    {
        $r->validate([
            'privacy_policy_url'     => 'nullable|string|max:300',
            'terms_url'              => 'nullable|string|max:300',
            'cookie_consent_enabled' => 'nullable|boolean',
            'cookie_consent_text'    => 'nullable|string|max:2000',
            'gdpr_enabled'           => 'nullable|boolean',
            'data_retention_days'    => 'nullable|integer|min:0|max:36500',
            'privacy_contact_email'  => 'nullable|string|max:180',
        ]);

        Setting::set('privacy_policy_url', $r->input('privacy_policy_url', ''));
        Setting::set('terms_url', $r->input('terms_url', ''));
        Setting::set('cookie_consent_enabled', (bool) $r->boolean('cookie_consent_enabled'), 'bool');
        Setting::set('cookie_consent_text', $r->input('cookie_consent_text', ''));
        Setting::set('gdpr_enabled', (bool) $r->boolean('gdpr_enabled'), 'bool');
        Setting::set('data_retention_days', (int) $r->input('data_retention_days', 0), 'int');
        Setting::set('privacy_contact_email', $r->input('privacy_contact_email', ''));

        return back()->with('success', __('Privacy settings saved.'));
    }
}
