<?php

use App\Http\Controllers\Account\UserAccessProbeController;
use App\Http\Controllers\Admin\AdminAuditEventController;
use App\Http\Controllers\Admin\AdminBillingCoverageController;
use App\Http\Controllers\Admin\AdminBoxController;
use App\Http\Controllers\Admin\AdminBoxItemController;
use App\Http\Controllers\Admin\AdminBoxSetupController;
use App\Http\Controllers\Admin\AdminCouponController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDeliveryController;
use App\Http\Controllers\Admin\AdminEmailResendController;
use App\Http\Controllers\Admin\AdminInflowScheduleController;
use App\Http\Controllers\Admin\AdminInventoryController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPaymentDiagnosticsController;
use App\Http\Controllers\Admin\AdminSearchController;
use App\Http\Controllers\Admin\AdminShopClosureController;
use App\Http\Controllers\Admin\AdminShopOrderController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminUpcomingChargeController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\ProducerController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SubcategoryController;
use App\Http\Controllers\BoxController;
use App\Http\Controllers\BoxWaitlistController;
use App\Http\Controllers\DashboardClickEventController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryPreferencesController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Marketing\AboutController;
use App\Http\Controllers\Marketing\ContactController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\PrivacyController;
use App\Http\Controllers\Marketing\ProducerController as MarketingProducerController;
use App\Http\Controllers\Marketing\ShopAboutController;
use App\Http\Controllers\Marketing\ShopContactController;
use App\Http\Controllers\Marketing\ShopHomeController;
use App\Http\Controllers\Marketing\TermsController;
use App\Http\Controllers\Onboarding\AccountStepController;
use App\Http\Controllers\Onboarding\AddressStepController;
use App\Http\Controllers\Onboarding\CommitmentStepController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Onboarding\PreferencesStepController;
use App\Http\Controllers\Onboarding\Subscription\PaymentStepController as SubscriptionWizardPaymentController;
use App\Http\Controllers\Onboarding\Subscription\ScheduleStepController as SubscriptionWizardScheduleController;
use App\Http\Controllers\Onboarding\Trial\DeliveryStepController as TrialWizardDeliveryController;
use App\Http\Controllers\Onboarding\Trial\PaymentStepController as TrialWizardPaymentController;
use App\Http\Controllers\Operations\CronRunController;
use App\Http\Controllers\Operations\HealthcheckController;
use App\Http\Controllers\PaymentDiagnosticsController;
use App\Http\Controllers\PaymentHistoryController;
use App\Http\Controllers\PaymentSetupController;
use App\Http\Controllers\ProductController as CustomerProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\Shop\ShopCatalogController;
use App\Http\Controllers\Shop\ShopController;
use App\Http\Controllers\SpargelAdminController;
use App\Http\Controllers\SpargelOrderController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Dev-only component gallery / brand playbook. Only mounted in local env so
// it never ships to production or staging routes.
if (app()->environment('local')) {
    Route::view('/dev/components', 'dev.components')->name('dev.components');
}

Route::name('public.')->group(function () {
    Route::get('/cron/run', CronRunController::class)->name('cron.run');
    // Shop-first public surface. The root URL, /erdgut and /kontakt make up
    // the shop-focused marketing site (shop header, no account chrome). The
    // pre-launch "coming soon" holding page (ComingSoonController +
    // marketing.coming-soon) is retired and no longer routed, but kept in
    // the codebase.
    Route::get('/', ShopHomeController::class)->name('home');
    Route::get('/erdgut', ShopAboutController::class)->name('shop-about');
    Route::get('/kontakt', [ShopContactController::class, 'create'])->name('shop-contact');
    Route::post('/kontakt', [ShopContactController::class, 'store'])->middleware('throttle:contact')->name('shop-contact.store');

    // Box waitlist — capture interest in the upcoming subscription boxes.
    // Homepage form + post-payment checkout prompt; one row per email.
    Route::post('/box-warteliste', [BoxWaitlistController::class, 'subscribe'])
        ->middleware('throttle:contact')
        ->name('box-waitlist.store');

    // Box / subscription marketing pages. The account-based subscription
    // product is not published yet, so these are parked under /box/* (kept
    // reachable so we don't lose them, but off the primary shop surface).
    // They keep the full marketing header with sign-up / sign-in.
    Route::get('/box/home', [HomeController::class, 'index'])->name('marketing-home');
    Route::get('/box/about', [AboutController::class, 'index'])->name('about');
    Route::get('/producers', [MarketingProducerController::class, 'index'])->name('producers');
    Route::get('/producers/{producer:slug}', [MarketingProducerController::class, 'show'])->name('producers.show');

    // Public catalogue: same controller as the auth-shell list/show under
    // /app/products. The controller renders the marketing-shell template
    // for guests, the app-shell template for authenticated users. Open
    // catalogue lets the marketing site link directly to a category
    // (e.g. the homepage chapter tiles) without forcing signup first.
    Route::get('/products', [CustomerProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [CustomerProductController::class, 'show'])->name('products.show');

    // ERD-354: editorial-only recipe surface keyed by slug. The dashboard
    // recipe-feature links here; a real Recipe model + admin CRUD lands
    // in ERD-358.
    Route::get('/recipes/{slug}', [RecipeController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')
        ->name('recipes.show');

    Route::get('/contact', [ContactController::class, 'create'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

    Route::get('/health', HealthcheckController::class)->name('health');

    // Browser-side telemetry intake from the SumUp card widget. Open
    // (no auth — the spargel checkout is anonymous) but throttled and
    // schema-validated. See PaymentDiagnosticsController for context.
    Route::post('/payment-diagnostics', [PaymentDiagnosticsController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('payment-diagnostics.store');

    Route::get('/locale/{locale}', [LocaleController::class, 'switch'])
        ->whereAlpha('locale')
        ->name('locale.switch');

    // Spargel trial-shop (retired): the single-product test channel has
    // been superseded by the general /shop. The entry view is disabled and
    // redirected to the shop so customers who bookmarked /spargel still land
    // somewhere useful. The store/pay/return routes stay live so any
    // in-flight SpargelOrder can still finish its SumUp checkout.
    Route::redirect('/spargel', '/shop')->name('spargel.show');
    Route::post('/spargel', [SpargelOrderController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('spargel.store');
    Route::get('/spargel/{spargelOrder}/pay', [SpargelOrderController::class, 'pay'])->name('spargel.pay');
    Route::get('/spargel/{spargelOrder}/return', [SpargelOrderController::class, 'return'])->name('spargel.return');

    // General shop: the admin-managed, multi-product successor to the
    // spargel channel. Same anonymous one-off-purchase model (no auth,
    // no User / Order linkage) but the cart spans the whole catalog and
    // the date picker is driven by live admin inventory. Spargel stays
    // live alongside it, untouched.
    Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
    // 20/min per IP: this endpoint only creates a *pending* order (the
    // SumUp charge happens later at /pay), and the multi-field cart form
    // means a guest can legitimately resubmit several times after
    // validation bounces — 6/min was too tight and surfaced "Too many
    // requests" mid-checkout. Still ample headroom against abuse.
    Route::post('/shop', [ShopController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('shop.store');
    // Live coupon preview: re-prices the cart server-side with a typed code
    // so the discount shown is the authoritative one (the same quote() the
    // order create + SumUp charge use). Throttled — it's an unauthenticated
    // endpoint that could otherwise be used to brute-force codes.
    Route::post('/shop/coupon/preview', [ShopController::class, 'couponPreview'])
        ->middleware('throttle:20,1')
        ->name('shop.coupon.preview');
    // Anonymous engagement beacons (impression / add-to-cart) feeding the
    // product-ranking bandit. Batched via navigator.sendBeacon; throttled.
    Route::post('/shop/track', [ShopController::class, 'trackEngagement'])
        ->middleware('throttle:60,1')
        ->name('shop.engagement');
    Route::get('/shop/{shopOrder}/pay', [ShopController::class, 'pay'])->name('shop.pay');
    Route::get('/shop/{shopOrder}/return', [ShopController::class, 'return'])->name('shop.return');
    // Post-payment box-waitlist answer (yes/no), recorded against the order
    // email. The order is already complete; this never blocks confirmation.
    Route::post('/shop/{shopOrder}/box-interesse', [BoxWaitlistController::class, 'recordInterest'])
        ->middleware('throttle:contact')
        ->name('shop.box-interest');
    // Stable per-order tracking page. Reached only via a signed URL (carried
    // in the confirmation / window emails) so order details can't be browsed
    // by incrementing the id. No expiry — the customer reopens it on the day.
    Route::get('/shop/{shopOrder}/track', [ShopController::class, 'track'])->name('shop.track');
    // PDF receipt (Kleinbetragsrechnung) — always in German. Signed URL
    // prevents order enumeration; no expiry so the customer can re-download.
    Route::get('/shop/{shopOrder}/receipt', [ShopController::class, 'receipt'])->name('shop.receipt');

    // Isolated catalog detail pages for the preview shop. Same body as the
    // public /products/{product} and /producers/{slug} pages but wrapped in
    // the self-contained shop chrome (no marketing header/footer, no links
    // out into the rest of the hidden project). The static `products` /
    // `producers` prefixes are declared before the `{shopOrder}` wildcard
    // routes above only in name, not in URL — these own distinct paths.
    Route::get('/shop/products', [ShopCatalogController::class, 'products'])->name('shop.products.index');
    Route::get('/shop/products/{product}', [ShopCatalogController::class, 'product'])->name('shop.products.show');
    Route::get('/shop/producers', [ShopCatalogController::class, 'producers'])->name('shop.producers.index');
    Route::get('/shop/producers/{producer:slug}', [ShopCatalogController::class, 'producer'])->name('shop.producers.show');

    // Shop-specific AGB — the storefront consent checkbox and footer link
    // here, not at the box/subscription /agb document.
    Route::get('/shop/agb', [TermsController::class, 'shopShow'])->name('shop.terms');

    // Impressum (§ 5 DDG) — mandatory provider identification, linked from
    // the shop footer.
    Route::get('/impressum', [TermsController::class, 'imprint'])->name('imprint');

    // Minimal privacy notice — exists so the spargel-form consent text
    // can link to a real page (Datenschutzhinweis is required for any
    // German B2C purchase). Replace with a full notice once the rest
    // of the marketing site is live.
    Route::get('/datenschutz', [PrivacyController::class, 'show'])->name('privacy');

    // Minimal AGB stub — exists so the payment-step consent checkbox
    // (B12 in UX review notes) can link to a real page. Replace with a
    // counsel-reviewed T&Cs document before launch.
    Route::get('/agb', [TermsController::class, 'show'])->name('terms');
});

// Onboarding wizard — guest-allowed entry, auth enforced after the account step.
Route::name('app.onboarding.')
    ->prefix('onboard')
    ->group(function () {
        Route::get('/', [OnboardingController::class, 'start'])->name('start');

        Route::get('/account', [AccountStepController::class, 'show'])->name('step.account');
        Route::post('/account', [AccountStepController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('step.account.store');

        // Auth-required steps. Two onboarding-specific middleware applied
        // to every step show-route:
        //   - onboarding.complete.redirect bounces customers who already
        //     have an active/pending Subscription back to dashboard or
        //     verify-email (the wizard is single-shot).
        //   - onboarding.step blocks forward URL jumps so a customer can't
        //     land on /payment without going through earlier steps.
        // POST .store handlers handle their own redirect via nextStep(), so
        // step-gating only fires on GET.
        Route::middleware(['auth', 'onboarding.complete.redirect'])->group(function () {
            Route::get('/address', [AddressStepController::class, 'show'])->middleware('onboarding.step')->name('step.address');
            Route::post('/address', [AddressStepController::class, 'store'])->name('step.address.store');

            Route::get('/preferences', [PreferencesStepController::class, 'show'])->middleware('onboarding.step')->name('step.preferences');
            Route::post('/preferences', [PreferencesStepController::class, 'store'])->name('step.preferences.store');

            Route::get('/commitment', [CommitmentStepController::class, 'show'])->middleware('onboarding.step')->name('step.commitment');
            Route::post('/commitment', [CommitmentStepController::class, 'store'])->name('step.commitment.store');

            Route::get('/trial/delivery', [TrialWizardDeliveryController::class, 'show'])->middleware('onboarding.step')->name('step.trial_delivery');
            Route::post('/trial/delivery', [TrialWizardDeliveryController::class, 'store'])->name('step.trial_delivery.store');

            Route::get('/trial/payment', [TrialWizardPaymentController::class, 'show'])->middleware('onboarding.step')->name('step.trial_payment');
            Route::get('/trial/payment/{order}/complete', [TrialWizardPaymentController::class, 'complete'])
                ->name('trial.payment.complete');
            Route::get('/trial/complete/{order}', [TrialWizardPaymentController::class, 'confirmation'])
                ->name('trial.complete');

            Route::get('/subscription/schedule', [SubscriptionWizardScheduleController::class, 'show'])->middleware('onboarding.step')->name('step.subscription_schedule');
            Route::post('/subscription/schedule', [SubscriptionWizardScheduleController::class, 'store'])->name('step.subscription_schedule.store');

            Route::get('/subscription/payment', [SubscriptionWizardPaymentController::class, 'show'])
                ->middleware(['throttle:payment-setup', 'onboarding.step'])
                ->name('step.subscription_payment');
            Route::get('/subscription/complete', [SubscriptionWizardPaymentController::class, 'complete'])
                ->middleware('throttle:payment-setup')
                ->name('subscription.complete');
        });
    });

Route::middleware(['auth', 'verified'])
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/dashboard/click', [DashboardClickEventController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('dashboard.click');

        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            // Legacy onboard routes kept as permanent redirects into the wizard.
            Route::get('/onboard', fn () => redirect()->route('app.onboarding.start', ['intent' => 'subscription']))->name('onboard');
            Route::post('/onboard', fn () => redirect()->route('app.onboarding.start', ['intent' => 'subscription']))->name('onboard.store');
            Route::get('/onboard/payment', fn () => redirect()->route('app.onboarding.start', ['intent' => 'subscription']))->name('onboard.payment');
            Route::get('/onboard/complete', fn () => redirect()->route('app.onboarding.start', ['intent' => 'subscription']))->name('onboard.complete');

            Route::get('/', [SubscriptionController::class, 'show'])->name('show');
            Route::get('/create', [SubscriptionController::class, 'create'])->name('create');
            Route::post('/', [SubscriptionController::class, 'store'])->name('store');
            Route::get('/edit', [SubscriptionController::class, 'edit'])->name('edit');
            Route::patch('/', [SubscriptionController::class, 'update'])->name('update');
            Route::post('/pause', [SubscriptionController::class, 'pause'])->middleware('throttle:subscription-actions')->name('pause');
            Route::post('/skip-next', [SubscriptionController::class, 'skipNextWeek'])->middleware('throttle:subscription-actions')->name('skip.next');
            Route::post('/skip-weeks', [SubscriptionController::class, 'updateSkipWeeks'])->middleware('throttle:subscription-actions')->name('skip.weeks');
            Route::post('/resume', [SubscriptionController::class, 'resume'])->middleware('throttle:subscription-actions')->name('resume');
            Route::post('/cancel', [SubscriptionController::class, 'cancel'])->middleware('throttle:subscription-actions')->name('cancel');
        });

        Route::get('products', [CustomerProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}', [CustomerProductController::class, 'show'])->name('products.show');

        Route::middleware('subscriber.full')->group(function () {
            Route::prefix('deliveries')->name('deliveries.')->group(function () {
                Route::get('/', [DeliveryController::class, 'index'])->name('index');
                Route::get('/preferences', [DeliveryPreferencesController::class, 'edit'])->name('preferences.edit');
                Route::patch('/preferences', [DeliveryPreferencesController::class, 'update'])->name('preferences.update');
                Route::get('/{delivery}', [DeliveryController::class, 'show'])->name('show');
                Route::get('/{delivery}/gift', [DeliveryController::class, 'gift'])->name('gift');
                Route::post('/{delivery}/gift', [DeliveryController::class, 'storeGift'])->name('gift.store');
            });
            Route::prefix('boxes')->name('boxes.')->group(function () {
                Route::get('/current', [BoxController::class, 'current'])->name('current');
                Route::get('/{box}', [BoxController::class, 'show'])->name('show');
            });
            Route::prefix('payments')->name('payments.')->group(function () {
                Route::get('/history', [PaymentHistoryController::class, 'index'])->name('history');
                Route::get('/update', [PaymentSetupController::class, 'showUpdate'])->middleware('throttle:payment-setup')->name('update');
            });
        });

        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/setup', [PaymentSetupController::class, 'show'])->middleware('throttle:payment-setup')->name('setup');
            Route::get('/setup/complete', [PaymentSetupController::class, 'complete'])->middleware('throttle:payment-setup')->name('setup.complete');
        });

        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
            Route::get('audit-events', [AdminAuditEventController::class, 'index'])->name('audit-events.index');
            Route::get('search', AdminSearchController::class)
                ->middleware('throttle:60,1')
                ->name('search');
            Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [AdminUserController::class, 'show'])
                ->whereNumber('user')
                ->name('users.show');
            Route::post('users', [AdminUserController::class, 'storeAdmin'])
                ->middleware('throttle:12,1')
                ->name('users.store-admin');
            Route::delete('users/{user}', [AdminUserController::class, 'destroy'])
                ->whereNumber('user')
                ->middleware('throttle:12,1')
                ->name('users.destroy');
            Route::patch('users/{user}/role', [AdminUserController::class, 'setRole'])
                ->whereNumber('user')
                ->middleware('throttle:12,1')
                ->name('users.set-role');
            Route::get('subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::post('deliveries/sync-from-boxes', [AdminDeliveryController::class, 'syncFromBoxes'])
                ->middleware('throttle:12,1')
                ->name('deliveries.sync-from-boxes');
            Route::get('deliveries/create', [AdminDeliveryController::class, 'create'])->name('deliveries.create');
            Route::post('deliveries', [AdminDeliveryController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('deliveries.store');
            Route::get('deliveries', [AdminDeliveryController::class, 'index'])->name('deliveries.index');
            Route::get('deliveries/{delivery}/edit', [AdminDeliveryController::class, 'edit'])->name('deliveries.edit');
            Route::patch('deliveries/{delivery}', [AdminDeliveryController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('deliveries.update');
            Route::patch('deliveries/{delivery}/status', [AdminDeliveryController::class, 'updateStatus'])
                ->middleware('throttle:30,1')
                ->name('deliveries.status.update');
            Route::post('deliveries/bulk-delay', [AdminDeliveryController::class, 'bulkDelay'])
                ->middleware('throttle:10,1')
                ->name('deliveries.bulk-delay');
            Route::post('deliveries/cleanup-orphans', [AdminDeliveryController::class, 'cleanupOrphans'])
                ->middleware('throttle:6,1')
                ->name('deliveries.cleanup-orphans');
            Route::delete('deliveries/{delivery}', [AdminDeliveryController::class, 'destroy'])
                ->middleware('throttle:30,1')
                ->name('deliveries.destroy');
            Route::get('subscriptions/create', [AdminSubscriptionController::class, 'create'])->name('subscriptions.create');
            Route::post('subscriptions', [AdminSubscriptionController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('subscriptions.store');
            Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/export', [AdminPaymentController::class, 'export'])->name('payments.export');
            Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
            Route::post('payments/{payment}/mark-paid-externally', [AdminPaymentController::class, 'markPaidExternally'])
                ->middleware('throttle:30,1')
                ->name('payments.mark-paid-externally');
            Route::post('payments/bulk-mark-paid-externally', [AdminPaymentController::class, 'bulkMarkPaidExternally'])
                ->middleware('throttle:10,1')
                ->name('payments.bulk-mark-paid-externally');
            Route::post('payments/{payment}/refund', [AdminPaymentController::class, 'refund'])
                ->middleware('throttle:20,1')
                ->name('payments.refund');
            Route::get('payment-diagnostics', [AdminPaymentDiagnosticsController::class, 'index'])
                ->name('payment-diagnostics.index');
            Route::post('emails/resend', [AdminEmailResendController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('emails.resend');
            Route::get('billing/upcoming', [AdminUpcomingChargeController::class, 'index'])->name('billing.upcoming');
            Route::get('billing/coverage', [AdminBillingCoverageController::class, 'index'])->name('billing.coverage');
            Route::post('billing/charge', [AdminBillingCoverageController::class, 'charge'])
                ->middleware('throttle:12,1')
                ->name('billing.charge');
            Route::get('boxes', [AdminBoxSetupController::class, 'index'])->name('boxes.index');
            Route::get('boxes/setup/{week}', [AdminBoxSetupController::class, 'show'])
                ->where('week', '\d{4}-\d{2}-\d{2}')
                ->name('boxes.setup.show');
            Route::post('boxes/setup/{week}', [AdminBoxSetupController::class, 'apply'])
                ->where('week', '\d{4}-\d{2}-\d{2}')
                ->middleware('throttle:12,1')
                ->name('boxes.setup.apply');
            Route::post('boxes/setup/{week}/reset', [AdminBoxSetupController::class, 'reset'])
                ->where('week', '\d{4}-\d{2}-\d{2}')
                ->middleware('throttle:6,1')
                ->name('boxes.setup.reset');
            Route::get('boxes/week/{week}', [AdminBoxSetupController::class, 'weekBoxes'])
                ->where('week', '\d{4}-\d{2}-\d{2}')
                ->name('boxes.week.show');

            Route::post('boxes/{box}/items', [AdminBoxItemController::class, 'store'])->name('boxes.items.store');
            Route::patch('boxes/{box}/items/{item}', [AdminBoxItemController::class, 'update'])
                ->scopeBindings()
                ->name('boxes.items.update');
            Route::delete('boxes/{box}/items/{item}', [AdminBoxItemController::class, 'destroy'])
                ->scopeBindings()
                ->name('boxes.items.destroy');
            Route::get('boxes/{box}', [AdminBoxController::class, 'show'])->name('boxes.show');
            Route::patch('boxes/{box}', [AdminBoxController::class, 'update'])
                ->middleware('throttle:24,1')
                ->name('boxes.update');
            Route::patch('boxes/{box}/meta', [AdminBoxController::class, 'updateMeta'])->name('boxes.meta.update');
            Route::post('boxes/{box}/override-edit', [AdminBoxController::class, 'overrideEdit'])
                ->middleware('throttle:30,1')
                ->name('boxes.override-edit');
            Route::resource('producers', ProducerController::class)->except(['show']);
            // Inventar — daily-inventory admin surface.
            Route::get('inventory', [AdminInventoryController::class, 'index'])
                ->name('inventory.index');
            Route::post('inventory/inflows', [AdminInventoryController::class, 'storeInflow'])
                ->middleware('throttle:30,1')
                ->name('inventory.inflows.store');
            Route::match(['put', 'patch'], 'inventory/inflows/{inflow}', [AdminInventoryController::class, 'updateInflow'])
                ->middleware('throttle:30,1')
                ->name('inventory.inflows.update');
            Route::delete('inventory/inflows/{inflow}', [AdminInventoryController::class, 'destroyInflow'])
                ->middleware('throttle:30,1')
                ->name('inventory.inflows.destroy');

            Route::post('inflow-schedules/{product}', [AdminInflowScheduleController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('inflow-schedules.store');
            Route::post('inflow-schedules/{product}/{schedule}/cancel', [AdminInflowScheduleController::class, 'cancel'])
                ->middleware('throttle:30,1')
                ->name('inflow-schedules.cancel');
            Route::post('inflow-schedules/{product}/{schedule}/adjust', [AdminInflowScheduleController::class, 'adjust'])
                ->middleware('throttle:30,1')
                ->name('inflow-schedules.adjust');

            Route::resource('products', ProductController::class)->except(['show']);
            Route::resource('subcategories', SubcategoryController::class)->except(['show']);

            Route::get('orders/create', [AdminOrderController::class, 'create'])->name('orders.create');
            Route::post('orders', [AdminOrderController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('orders.store');
            Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
            Route::post('orders/{order}/schedule', [AdminOrderController::class, 'schedule'])
                ->middleware('throttle:30,1')
                ->name('orders.schedule');
            Route::post('orders/{order}/compose', [AdminOrderController::class, 'compose'])
                ->middleware('throttle:30,1')
                ->name('orders.compose');
            Route::post('orders/{order}/mark-delivered', [AdminOrderController::class, 'markDelivered'])
                ->middleware('throttle:30,1')
                ->name('orders.mark-delivered');

            // Anonymous multi-product shop orders (the proper admin surface,
            // unlike the disposable /spargel-admin page).
            Route::get('shop-closures', [AdminShopClosureController::class, 'index'])->name('shop-closures.index');
            Route::get('shop-closures/calendar', [AdminShopClosureController::class, 'calendar'])->name('shop-closures.calendar');
            Route::post('shop-closures/toggle', [AdminShopClosureController::class, 'toggle'])
                ->middleware('throttle:60,1')
                ->name('shop-closures.toggle');

            Route::get('shop-orders', [AdminShopOrderController::class, 'index'])->name('shop-orders.index');
            // Manual order creation — declared before {shopOrder} wildcard.
            Route::get('shop-orders/create', [AdminShopOrderController::class, 'create'])->name('shop-orders.create');
            Route::get('shop-orders/available-products', [AdminShopOrderController::class, 'availableProductsForDate'])->name('shop-orders.available-products');
            Route::get('shop-orders/quote', [AdminShopOrderController::class, 'quoteForCreate'])->name('shop-orders.quote');
            // Excel export of the filtered orders set — declared before the
            // {shopOrder} wildcard so "export" isn't interpreted as a model id.
            Route::get('shop-orders/export', [AdminShopOrderController::class, 'export'])
                ->middleware('throttle:30,1')
                ->name('shop-orders.export');
            Route::post('shop-orders', [AdminShopOrderController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('shop-orders.store');
            Route::get('shop-orders/{shopOrder}', [AdminShopOrderController::class, 'show'])->name('shop-orders.show');
            Route::post('shop-orders/{shopOrder}/mark-paid', [AdminShopOrderController::class, 'markPaid'])
                ->middleware('throttle:30,1')
                ->name('shop-orders.mark-paid');
            Route::post('shop-orders/{shopOrder}/mark-completed', [AdminShopOrderController::class, 'markCompleted'])
                ->middleware('throttle:30,1')
                ->name('shop-orders.mark-completed');
            Route::post('shop-orders/{shopOrder}/refund', [AdminShopOrderController::class, 'refund'])
                ->middleware('throttle:30,1')
                ->name('shop-orders.refund');
            Route::post('shop-orders/{shopOrder}/delivery-window', [AdminShopOrderController::class, 'updateDeliveryWindow'])
                ->middleware('throttle:30,1')
                ->name('shop-orders.delivery-window');
            Route::delete('shop-orders/{shopOrder}', [AdminShopOrderController::class, 'destroy'])
                ->middleware('throttle:30,1')
                ->name('shop-orders.destroy');

            // Shop discount coupons (admin-managed): percentage or fixed,
            // with per-email + global redemption caps. See CouponService.
            Route::get('coupons', [AdminCouponController::class, 'index'])->name('coupons.index');
            Route::get('coupons/create', [AdminCouponController::class, 'create'])->name('coupons.create');
            Route::post('coupons', [AdminCouponController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('coupons.store');
            Route::get('coupons/{coupon}', [AdminCouponController::class, 'show'])->name('coupons.show');
            Route::put('coupons/{coupon}', [AdminCouponController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('coupons.update');
            Route::post('coupons/{coupon}/toggle', [AdminCouponController::class, 'toggle'])
                ->middleware('throttle:30,1')
                ->name('coupons.toggle');
            Route::delete('coupons/{coupon}', [AdminCouponController::class, 'destroy'])
                ->middleware('throttle:30,1')
                ->name('coupons.destroy');
        });
    });

Route::middleware('auth')
    ->prefix('account')
    ->name('account.')
    ->group(function () {
        Route::get('/users/{user}', [UserAccessProbeController::class, 'show'])->name('users.show');

        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'edit'])->name('edit');
            Route::patch('/', [ProfileController::class, 'update'])->name('update');
            Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
        });
    });

// Disposable spargel-trial admin view. Intentionally outside the
// `app.admin.*` group + not surfaced anywhere in the sidebar. Auth
// + admin-gated. Will be removed when the proper shop launches.
Route::middleware(['auth', 'can:admin'])
    ->prefix('spargel-admin')
    ->name('spargel.admin.')
    ->group(function () {
        Route::get('/', [SpargelAdminController::class, 'index'])->name('index');
        Route::post('/{spargelOrder}/mark-paid', [SpargelAdminController::class, 'markPaid'])
            ->middleware('throttle:30,1')
            ->name('mark-paid');
        Route::post('/{spargelOrder}/mark-completed', [SpargelAdminController::class, 'markCompleted'])
            ->middleware('throttle:30,1')
            ->name('mark-completed');
        Route::post('/{spargelOrder}/refund', [SpargelAdminController::class, 'refund'])
            ->middleware('throttle:30,1')
            ->name('refund');
        Route::delete('/{spargelOrder}', [SpargelAdminController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('destroy');
    });

require __DIR__.'/auth.php';
