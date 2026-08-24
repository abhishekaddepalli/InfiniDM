<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\InstagramSettingsController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Install wizard — public, pre-auth
|--------------------------------------------------------------------------
|
| Standalone only. Until storage/installed.lock exists the EnsureInstalled
| middleware (bootstrap/app.php) redirects every other route here, so a client
| who just uploaded the pre-built package lands on the wizard, fills in DB +
| admin details, and it builds the database itself — no terminal commands.
*/
Route::get('/install',          [InstallController::class, 'index'])->name('install');
Route::post('/install/test-db', [InstallController::class, 'testDatabase'])->name('install.test-db');
Route::post('/install/verify-license', [InstallController::class, 'verifyLicense'])->name('install.verify-license');
Route::post('/install',         [InstallController::class, 'install'])->name('install.run');
Route::get('/install/status',   [InstallController::class, 'status'])->name('install.status');

/*
|--------------------------------------------------------------------------
| Instaflow standalone — the shell WaDesk used to supply
|--------------------------------------------------------------------------
|
| Standalone-only. Core WaDesk already owns a routes/web.php, so this file
| lives under standalone/ (in build.py's SKIP_DIRS) and is overlaid at the
| archive root only by the standalone build. Putting it in the shared routes/
| directory would make assert_no_core_collisions() refuse the addon archive.
|
| It registers as little as possible on purpose. The entire Instagram surface
| — roughly 120 routes — comes from the shared routes/instagram.php, which
| bootstrap/app.php mounts from its `then:` callback. Re-declaring any of it
| here would give two routes the same name; the later registration wins and
| the shared one is silently shadowed, which is very hard to see afterwards.
|
| What is left is the shell: a way in, a way out, and the route NAMES the
| shared Blade views resolve at render time. Only two of those are not
| instagram.*, and both are satisfied below:
|
|   logout                          components/layouts/instagram.blade.php
|   admin.settings.instagram.update admin/settings/instagram.blade.php
|
| A name the views call but nothing registers is a RouteNotFoundException —
| a 500 on every page that uses the layout, not a 404 on one link.
|
| No route here uses a Closure, for action or for middleware. `route:cache`
| refuses to serialize a Closure, and a production install that cannot cache
| its routes pays for it on every request.
|
|--------------------------------------------------------------------------
| CONTRACT — controllers this file names but does not own
|--------------------------------------------------------------------------
|
|   Auth\LoginController     show  store  destroy
|   Auth\RegisterController  show  store
|
| Renaming any class or method above has to happen in the same commit as this
| file. PHP does not autoload a ::class constant, so a mismatch stays quiet
| until someone actually opens the page.
|
| There is no password reset. No controller, no views, no link on the sign-in
| form, and .env.example ships MAIL_MAILER=log, so a reset mail would not leave
| the box on a fresh install anyway. Routes named password.* were registered
| here once against a class that was never written; they are gone rather than
| stubbed, because a named route pointing at a missing class survives both
| route registration and `route:cache` and only fails when someone opens it.
| Building the flow means adding the controller, the two views and the four
| framework-fixed names together — password.reset in particular must keep its
| {token} segment, since Laravel's ResetPassword notification builds its link
| from it.
*/

/*
|--------------------------------------------------------------------------
| Public marketing site — home, about, contact, blog, legal
|--------------------------------------------------------------------------
|
| Renders for BOTH guests and signed-in users (the header swaps Sign-in for a
| Dashboard button), so `/` never loops even though the guest middleware sends
| authenticated visitors here. Every line of copy is admin-editable through the
| FrontContent registry (admin → Front pages). No Closures — route:cache safe.
*/
Route::get('/', [FrontendController::class, 'home'])->name('home');
Route::get('/features',         [FrontendController::class, 'features'])->name('page.features');
Route::get('/pricing',          [FrontendController::class, 'pricing'])->name('page.pricing');
Route::get('/about',            [FrontendController::class, 'about'])->name('page.about');
Route::get('/contact',          [FrontendController::class, 'contact'])->name('page.contact');
Route::post('/contact',         [FrontendController::class, 'contactStore'])->middleware('throttle:8,1')->name('page.contact.store');
Route::get('/blog',             [FrontendController::class, 'blog'])->name('blog.index');
Route::get('/blog/{slug}',      [FrontendController::class, 'blogShow'])->name('blog.show');
Route::get('/legal/{type}',     [FrontendController::class, 'legal'])->where('type', 'terms|privacy|cookies|refund')->name('page.legal');
// PWA — dynamic manifest + service worker (installable public site).
Route::get('/manifest.webmanifest', [FrontendController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js',                 [FrontendController::class, 'serviceWorker'])->name('pwa.sw');

// Language switch from the header dropdown — works for guests and signed-in
// users (persists to users.locale when authenticated). Plain web POST.
Route::post('/locale', [\App\Http\Controllers\LocaleController::class, 'update'])->name('locale.update');

/*
|--------------------------------------------------------------------------
| Guest — sign in, sign up
|--------------------------------------------------------------------------
|
| `login` is not optional as a name: Laravel's Authenticate middleware builds
| its redirect from route('login'), and every Instagram page sits behind
| `auth`. Without it an expired session throws instead of redirecting.
|
| Registration is registered unconditionally and ALLOW_REGISTRATION is
| enforced inside RegisterController, not here. Gating the registration at
| route-definition time would bake the answer into a cached route table, and
| env() does not survive `config:cache` at all — the flag would silently
| revert to its default on exactly the installs that tune for production.
*/
Route::middleware('guest')->group(function () {
    // Only the GET legs carry names: both Blade forms post to route('login')
    // and route('register'), which resolve to these URIs, and an unnamed POST
    // sharing a URI with a named GET is what keeps that working.
    Route::get('/login',  [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/register',  [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    // Password reset — the four framework-fixed names. password.reset MUST keep
    // its {token} segment (Laravel's ResetPassword notification builds its link
    // from it). Mails go out via the SMTP configured at /admin/settings/mail
    // (MailConfig::apply() runs at boot).
    Route::get('/forgot-password',        [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'showLinkRequest'])->name('password.request');
    Route::post('/forgot-password',       [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password',        [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Signed in
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // The rail's back-arrow points at /dashboard, and it is where the guest
    // middleware bounces an authenticated visitor. Standalone has only the one
    // dashboard, so this hands straight off to the Instagram one.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Platform settings — Meta app credentials
|--------------------------------------------------------------------------
|
| routes/instagram.php registers this same pair inside WaDesk's admin group,
| but only when the `admin` middleware alias exists. Standalone has no WaDesk
| admin surface and never registers that alias, so the pair is skipped there —
| which strands the install: InstagramService, InstagramConnectController and
| InstagramWebhookController all read the app_id / app_secret / verify_token
| SystemSetting rows, and nothing else writes them.
|
| The condition is the exact inverse of the shared file's so the two can never
| both register and shadow each other, whichever way the alias goes. Names and
| controller are identical, which is what keeps the shared settings view's
| route('admin.settings.instagram.update') resolving in both products.
|
| Middleware is only `auth`. WaDesk layers `admin` + `ip.allowlist` on top;
| standalone has neither alias, and no role system to stand in for them. That
| gap is real and deliberate rather than overlooked — see the note handed back
| with this file. With ALLOW_REGISTRATION=false, which .env.example documents
| as the posture once the team is created, only invited accounts reach it.
*/
if (! (app('router')->getMiddleware()['admin'] ?? null)) {
    Route::middleware('auth')
        ->prefix('admin/settings')
        ->name('admin.settings.')
        ->group(function () {
            Route::get('/instagram',  [InstagramSettingsController::class, 'index'])->name('instagram');
            Route::post('/instagram', [InstagramSettingsController::class, 'update'])->name('instagram.update');
        });
}

/*
|--------------------------------------------------------------------------
| SaaS admin
|--------------------------------------------------------------------------
|
| The operator area. 'admin.gate' 404s any non-admin, so these URLs are
| invisible to customers. A lean set — overview, users, packages, settings,
| analytics — restyled from WaDesk's admin onto the Instagram tokens.
*/
Route::middleware(['auth', 'admin.gate'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',           [AdminController::class, 'overview'])->name('overview');
    Route::get('/analytics',  [AdminController::class, 'analytics'])->name('analytics');

    // Unified dashboard — Overview / Financial / Premium / Analytics / AI in tabs.
    Route::get('/insights',      [\App\Http\Controllers\Admin\InsightsController::class, 'index'])->name('insights');

    // Main-menu dashboards.
    Route::get('/financial',     [\App\Http\Controllers\Admin\FinancialController::class, 'financial'])->name('financial');
    Route::get('/premium',       [\App\Http\Controllers\Admin\FinancialController::class, 'premium'])->name('premium');
    Route::get('/ai-dashboard',  [\App\Http\Controllers\Admin\SystemDashController::class, 'aiDashboard'])->name('ai');
    Route::get('/system-health', [\App\Http\Controllers\Admin\SystemDashController::class, 'systemHealth'])->name('health');

    Route::get('/roles',              [\App\Http\Controllers\Admin\RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create',       [\App\Http\Controllers\Admin\RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles',             [\App\Http\Controllers\Admin\RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit',  [\App\Http\Controllers\Admin\RoleController::class, 'edit'])->name('roles.edit');
    Route::patch('/roles/{role}',     [\App\Http\Controllers\Admin\RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}',    [\App\Http\Controllers\Admin\RoleController::class, 'destroy'])->name('roles.destroy');

    Route::get('/users',              [AdminController::class, 'users'])->name('users');
    Route::get('/users/create',       [AdminController::class, 'createUser'])->name('users.create');
    Route::post('/users',             [AdminController::class, 'storeUser'])->name('users.store');
    Route::get('/users/{user}/edit',  [AdminController::class, 'editUser'])->name('users.edit');
    Route::patch('/users/{user}',     [AdminController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{user}',    [AdminController::class, 'destroyUser'])->name('users.destroy');

    Route::get('/packages',                 [PackageController::class, 'index'])->name('packages.index');
    Route::get('/packages/create',          [PackageController::class, 'create'])->name('packages.create');
    Route::post('/packages',                [PackageController::class, 'store'])->name('packages.store');
    Route::get('/packages/{package}/edit',   [PackageController::class, 'edit'])->name('packages.edit');
    Route::put('/packages/{package}',        [PackageController::class, 'update'])->name('packages.update');
    Route::post('/packages/{package}/toggle',[PackageController::class, 'toggle'])->name('packages.toggle');
    Route::delete('/packages/{package}',     [PackageController::class, 'destroy'])->name('packages.destroy');

    // Billing — order history, invoices, billing settings, wallet & affiliate
    Route::get('/order-history',     [\App\Http\Controllers\Admin\BillingListController::class, 'orderHistory'])->name('orders');
    Route::get('/invoices',          [\App\Http\Controllers\Admin\BillingListController::class, 'invoices'])->name('invoices');
    Route::post('/invoices',         [\App\Http\Controllers\Admin\BillingListController::class, 'storeInvoice'])->name('invoices.store');
    Route::get('/invoices/{order}/view', [\App\Http\Controllers\Admin\BillingListController::class, 'showInvoice'])->whereNumber('order')->name('invoices.view');
    Route::get('/billing-settings',  [\App\Http\Controllers\Admin\BillingSettingsController::class, 'billing'])->name('billing');
    Route::post('/billing-settings', [\App\Http\Controllers\Admin\BillingSettingsController::class, 'billingSave'])->name('billing.save');

    // Pricing FAQ — admin-editable accordion on the plans page.
    Route::get('/pricing-faqs',             [\App\Http\Controllers\Admin\PricingFaqController::class, 'index'])->name('pricing-faqs');
    Route::post('/pricing-faqs',            [\App\Http\Controllers\Admin\PricingFaqController::class, 'store'])->name('pricing-faqs.store');
    Route::put('/pricing-faqs/{id}',        [\App\Http\Controllers\Admin\PricingFaqController::class, 'update'])->whereNumber('id')->name('pricing-faqs.update');
    Route::post('/pricing-faqs/{id}/toggle',[\App\Http\Controllers\Admin\PricingFaqController::class, 'toggle'])->whereNumber('id')->name('pricing-faqs.toggle');
    Route::delete('/pricing-faqs/{id}',     [\App\Http\Controllers\Admin\PricingFaqController::class, 'destroy'])->whereNumber('id')->name('pricing-faqs.destroy');

    // Plan orders — manual (offline / bank-transfer) approval queue for plan purchases.
    Route::get('/plan-orders',                  [\App\Http\Controllers\Admin\PlanOrderController::class, 'index'])->name('plan-orders');
    Route::post('/plan-orders/{order}/approve', [\App\Http\Controllers\Admin\PlanOrderController::class, 'approve'])->whereNumber('order')->name('plan-orders.approve');
    Route::post('/plan-orders/{order}/reject',  [\App\Http\Controllers\Admin\PlanOrderController::class, 'reject'])->whereNumber('order')->name('plan-orders.reject');

    // Payment gateways — enable + configure the checkout gateways.
    Route::get('/payment-gateways',              [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
    Route::patch('/payment-gateways/{id}',       [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'update'])->whereNumber('id')->name('payment-gateways.update');
    Route::post('/payment-gateways/{id}/toggle', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'toggle'])->whereNumber('id')->name('payment-gateways.toggle');

    // Flow templates
    Route::get('/flow-templates',                     [\App\Http\Controllers\Admin\FlowTemplateController::class, 'index'])->name('flow-templates.index');
    Route::get('/flow-templates/create',              [\App\Http\Controllers\Admin\FlowTemplateController::class, 'create'])->name('flow-templates.create');
    Route::post('/flow-templates',                    [\App\Http\Controllers\Admin\FlowTemplateController::class, 'store'])->name('flow-templates.store');
    Route::get('/flow-templates/{flowTemplate}/edit', [\App\Http\Controllers\Admin\FlowTemplateController::class, 'edit'])->name('flow-templates.edit');
    Route::put('/flow-templates/{flowTemplate}',      [\App\Http\Controllers\Admin\FlowTemplateController::class, 'update'])->name('flow-templates.update');
    Route::delete('/flow-templates/{flowTemplate}',   [\App\Http\Controllers\Admin\FlowTemplateController::class, 'destroy'])->name('flow-templates.destroy');

    // Billing & plans — coupons
    Route::get('/coupons',                [\App\Http\Controllers\Admin\CouponController::class, 'index'])->name('coupons.index');
    Route::get('/coupons/create',         [\App\Http\Controllers\Admin\CouponController::class, 'create'])->name('coupons.create');
    Route::post('/coupons',               [\App\Http\Controllers\Admin\CouponController::class, 'store'])->name('coupons.store');
    Route::get('/coupons/{coupon}/edit',  [\App\Http\Controllers\Admin\CouponController::class, 'edit'])->name('coupons.edit');
    Route::put('/coupons/{coupon}',       [\App\Http\Controllers\Admin\CouponController::class, 'update'])->name('coupons.update');
    Route::delete('/coupons/{coupon}',    [\App\Http\Controllers\Admin\CouponController::class, 'destroy'])->name('coupons.destroy');

    // Marketing — announcements
    Route::get('/announcements',                       [\App\Http\Controllers\Admin\AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/create',                [\App\Http\Controllers\Admin\AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/announcements',                      [\App\Http\Controllers\Admin\AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('/announcements/{announcement}/edit',   [\App\Http\Controllers\Admin\AnnouncementController::class, 'edit'])->name('announcements.edit');
    Route::match(['put', 'patch'], '/announcements/{announcement}', [\App\Http\Controllers\Admin\AnnouncementController::class, 'update'])->name('announcements.update');
    Route::post('/announcements/{announcement}/toggle', [\App\Http\Controllers\Admin\AnnouncementController::class, 'toggle'])->name('announcements.toggle');
    Route::delete('/announcements/{announcement}',     [\App\Http\Controllers\Admin\AnnouncementController::class, 'destroy'])->name('announcements.destroy');


    // Localization — currencies + languages
    Route::get('/currencies',                    [\App\Http\Controllers\Admin\LocalizationController::class, 'currencies'])->name('currencies.index');
    Route::post('/currencies',                   [\App\Http\Controllers\Admin\LocalizationController::class, 'storeCurrency'])->name('currencies.store');
    Route::post('/currencies/fetch-rates',       [\App\Http\Controllers\Admin\LocalizationController::class, 'fetchRates'])->name('currencies.fetch-rates');
    Route::post('/currencies/default',           [\App\Http\Controllers\Admin\LocalizationController::class, 'setCurrencyDefault'])->name('currencies.default');
    Route::post('/currencies/{currency}/toggle', [\App\Http\Controllers\Admin\LocalizationController::class, 'toggleCurrency'])->name('currencies.toggle');
    Route::delete('/currencies/{currency}',      [\App\Http\Controllers\Admin\LocalizationController::class, 'destroyCurrency'])->name('currencies.destroy');
    Route::get('/languages',                    [\App\Http\Controllers\Admin\LocalizationController::class, 'languages'])->name('languages.index');
    Route::post('/languages/{language}/toggle',  [\App\Http\Controllers\Admin\LocalizationController::class, 'toggleLanguage'])->name('languages.toggle');
    Route::post('/languages/{language}/default', [\App\Http\Controllers\Admin\LocalizationController::class, 'setLanguageDefault'])->name('languages.default');
    Route::delete('/languages/{language}',       [\App\Http\Controllers\Admin\LocalizationController::class, 'destroyLanguage'])->name('languages.destroy');

    // System — security + storage
    Route::get('/security',   [\App\Http\Controllers\Admin\SystemController::class, 'security'])->name('security');
    Route::post('/security',  [\App\Http\Controllers\Admin\SystemController::class, 'securitySave'])->name('security.save');
    Route::get('/storage',      [\App\Http\Controllers\Admin\SystemController::class, 'storage'])->name('storage');
    Route::post('/storage',     [\App\Http\Controllers\Admin\SystemController::class, 'storageSave'])->name('storage.save');
    Route::post('/storage/test',[\App\Http\Controllers\Admin\SystemController::class, 'storageTest'])->name('storage.test');

    Route::get('/settings',  [SettingController::class, 'show'])->name('settings');
    Route::post('/settings', [SettingController::class, 'save'])->name('settings.save');

    // Front pages — edit every line of the public marketing site.
    Route::get('/front',  [\App\Http\Controllers\Admin\FrontPageController::class, 'index'])->name('front');
    Route::post('/front', [\App\Http\Controllers\Admin\FrontPageController::class, 'save'])->name('front.save');
    Route::post('/front/messages/{message}/read', [\App\Http\Controllers\Admin\FrontPageController::class, 'markRead'])->whereNumber('message')->name('front.message.read');

    // Mail settings — SMTP + test send.
    Route::get('/settings/mail',       [\App\Http\Controllers\Admin\MailController::class, 'show'])->name('settings.mail');
    Route::patch('/settings/mail',     [\App\Http\Controllers\Admin\MailController::class, 'update'])->name('settings.mail.update');
    Route::post('/settings/mail/test', [\App\Http\Controllers\Admin\MailController::class, 'test'])->name('settings.mail.test');

    // SEO / Analytics / Privacy settings.
    Route::get('/settings/seo',        [\App\Http\Controllers\Admin\SeoAnalyticsController::class, 'seo'])->name('settings.seo');
    Route::post('/settings/seo',       [\App\Http\Controllers\Admin\SeoAnalyticsController::class, 'seoSave'])->name('settings.seo.save');
    Route::get('/settings/analytics',  [\App\Http\Controllers\Admin\SeoAnalyticsController::class, 'analytics'])->name('settings.analytics');
    Route::post('/settings/analytics', [\App\Http\Controllers\Admin\SeoAnalyticsController::class, 'analyticsSave'])->name('settings.analytics.save');
    Route::get('/settings/privacy',    [\App\Http\Controllers\Admin\SeoAnalyticsController::class, 'privacy'])->name('settings.privacy');
    Route::post('/settings/privacy',   [\App\Http\Controllers\Admin\SeoAnalyticsController::class, 'privacySave'])->name('settings.privacy.save');

    // Social login + Site settings.
    Route::get('/settings/social-login',  [\App\Http\Controllers\Admin\SocialSiteController::class, 'socialLogin'])->name('settings.social');
    Route::post('/settings/social-login', [\App\Http\Controllers\Admin\SocialSiteController::class, 'socialLoginSave'])->name('settings.social.save');
    Route::get('/site-settings',          [\App\Http\Controllers\Admin\SocialSiteController::class, 'siteSettings'])->name('site');
    Route::post('/site-settings',         [\App\Http\Controllers\Admin\SocialSiteController::class, 'siteSettingsSave'])->name('site.save');

    // Audit log + Update.
    Route::get('/audit-log', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit');
    Route::get('/update',    [\App\Http\Controllers\Admin\UpdateController::class, 'show'])->name('update');
    // Updater steps: verify purchase → backup → upload → apply → migrate → finalize (+ rollback).
    Route::post('/update/verify',   [\App\Http\Controllers\Admin\UpdateController::class, 'verify'])->name('update.verify');
    Route::post('/update/backup',   [\App\Http\Controllers\Admin\UpdateController::class, 'backup'])->name('update.backup');
    Route::post('/update/upload',   [\App\Http\Controllers\Admin\UpdateController::class, 'upload'])->name('update.upload');
    Route::post('/update/apply',    [\App\Http\Controllers\Admin\UpdateController::class, 'apply'])->name('update.apply');
    Route::post('/update/migrate',  [\App\Http\Controllers\Admin\UpdateController::class, 'migrate'])->name('update.migrate');
    Route::post('/update/finalize', [\App\Http\Controllers\Admin\UpdateController::class, 'finalize'])->name('update.finalize');
    Route::post('/update/rollback', [\App\Http\Controllers\Admin\UpdateController::class, 'rollback'])->name('update.rollback');

    // AI provider keys — global fallback keys for the flow "AI" node.
    Route::get('/api-keys',                [\App\Http\Controllers\Admin\AdminAiKeyController::class, 'index'])->name('api-keys.index');
    Route::patch('/api-keys/{id}',         [\App\Http\Controllers\Admin\AdminAiKeyController::class, 'update'])->whereNumber('id')->name('api-keys.update');
    Route::post('/api-keys/{id}/toggle',   [\App\Http\Controllers\Admin\AdminAiKeyController::class, 'toggle'])->whereNumber('id')->name('api-keys.toggle');
});

/*
|--------------------------------------------------------------------------
| Developer file-sync (private) — push code to the server
|--------------------------------------------------------------------------
| A remote file-write endpoint used to deploy local changes without a manual
| cPanel upload. It is INERT unless WD_SYNC_KEY is set in .env, and authed by
| that key in the X-Sync-Key header (see WdSyncController). Deliberately outside
| the `web` group's session/CSRF — it's a machine-to-machine call with no
| cookie. The route path is unremarkable on purpose; the key is the access
| control. Same endpoint WaDesk uses so the local push script is identical.
*/
Route::post('/wd-sync', [\App\Http\Controllers\WdSyncController::class, 'push'])
    ->name('wd.sync')
    ->middleware('throttle:30,1');
