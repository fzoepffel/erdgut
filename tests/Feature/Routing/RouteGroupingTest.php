<?php

namespace Tests\Feature\Routing;

use App\Http\Controllers\Admin\AdminBillingCoverageController;
use App\Http\Controllers\Admin\AdminBoxController;
use App\Http\Controllers\Admin\AdminBoxItemController;
use App\Http\Controllers\Admin\AdminBoxSetupController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDeliveryController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminUpcomingChargeController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\BoxController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryPreferencesController;
use App\Http\Controllers\Marketing\AboutController;
use App\Http\Controllers\Marketing\ComingSoonController;
use App\Http\Controllers\Marketing\ContactController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\ProducerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SubscriptionController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class RouteGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_named_routes_use_expected_paths(): void
    {
        $this->assertSame('/', route('public.home', absolute: false));
        $this->assertSame('/home', route('public.marketing-home', absolute: false));
        $this->assertSame('/about', route('public.about', absolute: false));
        $this->assertSame('/producers', route('public.producers', absolute: false));
        $this->assertSame('/contact', route('public.contact', absolute: false));
        $this->assertSame('/health', route('public.health', absolute: false));
        $this->assertSame('/app/dashboard', route('app.dashboard', absolute: false));
        $this->assertSame('/app/subscriptions', route('app.subscriptions.show', absolute: false));
        $this->assertSame('/app/subscriptions/create', route('app.subscriptions.create', absolute: false));
        $this->assertSame('/app/subscriptions', route('app.subscriptions.store', absolute: false));
        $this->assertSame('/app/subscriptions/edit', route('app.subscriptions.edit', absolute: false));
        $this->assertSame('/app/subscriptions', route('app.subscriptions.update', absolute: false));
        $this->assertSame('/app/subscriptions/pause', route('app.subscriptions.pause', absolute: false));
        $this->assertSame('/app/subscriptions/resume', route('app.subscriptions.resume', absolute: false));
        $this->assertSame('/app/subscriptions/cancel', route('app.subscriptions.cancel', absolute: false));
        $this->assertSame('/app/subscriptions/onboard', route('app.subscriptions.onboard', absolute: false));
        $this->assertSame('/app/subscriptions/onboard', route('app.subscriptions.onboard.store', absolute: false));
        $this->assertSame('/app/subscriptions/onboard/payment', route('app.subscriptions.onboard.payment', absolute: false));
        $this->assertSame('/app/subscriptions/onboard/complete', route('app.subscriptions.onboard.complete', absolute: false));
        $this->assertSame('/app/payments/history', route('app.payments.history', absolute: false));
        $this->assertSame('/app/boxes/current', route('app.boxes.current', absolute: false));
        $this->assertSame('/app/boxes/1', route('app.boxes.show', ['box' => 1], absolute: false));
        $this->assertSame('/app/deliveries/1', route('app.deliveries.show', ['delivery' => 1], absolute: false));
        $this->assertSame('/app/deliveries/1/gift', route('app.deliveries.gift', ['delivery' => 1], absolute: false));
        $this->assertSame('/app/deliveries/preferences', route('app.deliveries.preferences.edit', absolute: false));
        $this->assertSame('/app/deliveries/preferences', route('app.deliveries.preferences.update', absolute: false));
        $this->assertSame('/app/products', route('app.products.index', absolute: false));
        $this->assertSame('/app/products/1', route('app.products.show', ['product' => 1], absolute: false));
        $this->assertSame('/app/subscriptions/skip-next', route('app.subscriptions.skip.next', absolute: false));
        $this->assertSame('/app/admin/deliveries/1/status', route('app.admin.deliveries.status.update', ['delivery' => 1], absolute: false));
        $this->assertSame('/app/payments/setup', route('app.payments.setup', absolute: false));
        $this->assertSame('/app/payments/setup/complete', route('app.payments.setup.complete', absolute: false));
        $this->assertSame('/app/payments/update', route('app.payments.update', absolute: false));
        $this->assertSame('/app/admin', route('app.admin.dashboard', absolute: false));
        $this->assertSame('/app/admin/users', route('app.admin.users.index', absolute: false));
        $this->assertSame('/app/admin/subscriptions', route('app.admin.subscriptions.index', absolute: false));
        $this->assertSame('/app/admin/deliveries', route('app.admin.deliveries.index', absolute: false));
        $this->assertSame('/app/admin/payments', route('app.admin.payments.index', absolute: false));
        $this->assertSame('/app/admin/billing/upcoming', route('app.admin.billing.upcoming', absolute: false));
        $this->assertSame('/app/admin/billing/coverage', route('app.admin.billing.coverage', absolute: false));
        $this->assertSame('/app/admin/billing/charge', route('app.admin.billing.charge', absolute: false));
        $this->assertSame('/app/admin/boxes', route('app.admin.boxes.index', absolute: false));
        $this->assertSame('/app/admin/boxes/setup/2026-04-27', route('app.admin.boxes.setup.show', ['week' => '2026-04-27'], absolute: false));
        $this->assertSame('/app/admin/boxes/setup/2026-04-27', route('app.admin.boxes.setup.apply', ['week' => '2026-04-27'], absolute: false));
        $this->assertSame('/app/admin/boxes/week/2026-04-27', route('app.admin.boxes.week.show', ['week' => '2026-04-27'], absolute: false));
        $this->assertSame('/app/admin/boxes/1/items', route('app.admin.boxes.items.store', ['box' => 1], absolute: false));
        $this->assertSame('/app/admin/boxes/1/items/2', route('app.admin.boxes.items.update', ['box' => 1, 'item' => 2], absolute: false));
        $this->assertSame('/app/admin/boxes/1/items/2', route('app.admin.boxes.items.destroy', ['box' => 1, 'item' => 2], absolute: false));
        $this->assertSame('/app/admin/boxes/1', route('app.admin.boxes.show', ['box' => 1], absolute: false));
        $this->assertSame('/api/sumup/webhook', route('sumup.webhook', absolute: false));
        $this->assertSame('/account/profile', route('account.profile.edit', absolute: false));
        $this->assertSame('/auth/login', route('auth.login', absolute: false));
        $this->assertSame('/auth/register', route('auth.register', absolute: false));
        $this->assertSame('/auth/logout', route('auth.logout', absolute: false));

        // Keep default Laravel route names used by auth internals.
        $this->assertSame('/auth/forgot-password', route('password.request', absolute: false));
        $this->assertSame('/auth/verify-email', route('verification.notice', absolute: false));
    }

    public function test_guest_is_redirected_to_auth_login_for_protected_pages(): void
    {
        $this->get('/app/dashboard')->assertRedirect('/auth/login');
        $this->get('/app/admin')->assertRedirect('/auth/login');
        $this->get('/account/profile')->assertRedirect('/auth/login');
    }

    public function test_signed_middleware_is_active_for_email_verification_route(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/auth/verify-email/'.$user->id.'/invalid-signature')
            ->assertForbidden();
    }

    public function test_critical_routes_keep_expected_middleware_contracts(): void
    {
        $dashboardRoute = RouteFacade::getRoutes()->getByName('app.dashboard');
        $verificationRoute = RouteFacade::getRoutes()->getByName('verification.verify');
        $verificationSendRoute = RouteFacade::getRoutes()->getByName('verification.send');

        $this->assertNotNull($dashboardRoute);
        $this->assertNotNull($verificationRoute);
        $this->assertNotNull($verificationSendRoute);

        $this->assertContains('auth', $dashboardRoute->gatherMiddleware());
        $this->assertContains('verified', $dashboardRoute->gatherMiddleware());

        $this->assertContains('auth', $verificationRoute->gatherMiddleware());
        $this->assertContains('signed', $verificationRoute->gatherMiddleware());
        $this->assertContains('throttle:6,1', $verificationRoute->gatherMiddleware());

        $this->assertContains('auth', $verificationSendRoute->gatherMiddleware());
        $this->assertContains('throttle:6,1', $verificationSendRoute->gatherMiddleware());

        $sumUpWebhookRoute = RouteFacade::getRoutes()->getByName('sumup.webhook');
        $this->assertNotNull($sumUpWebhookRoute);
        $this->assertContains('sumup.webhook.verify:subscription', $sumUpWebhookRoute->gatherMiddleware());
        $this->assertNotContains('auth', $sumUpWebhookRoute->gatherMiddleware());

        $adminDashboardRoute = RouteFacade::getRoutes()->getByName('app.admin.dashboard');
        $this->assertNotNull($adminDashboardRoute);
        $this->assertContains('auth', $adminDashboardRoute->gatherMiddleware());
        $this->assertContains('verified', $adminDashboardRoute->gatherMiddleware());
        $this->assertContains('admin', $adminDashboardRoute->gatherMiddleware());

        $adminOpsRoutes = [
            'app.admin.users.index',
            'app.admin.subscriptions.index',
            'app.admin.deliveries.index',
            'app.admin.boxes.index',
            'app.admin.boxes.setup.show',
            'app.admin.boxes.week.show',
            'app.admin.boxes.show',
            'app.admin.payments.index',
            'app.admin.billing.upcoming',
            'app.admin.billing.coverage',
        ];

        foreach ($adminOpsRoutes as $name) {
            $route = RouteFacade::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('auth', $route->gatherMiddleware(), $name);
            $this->assertContains('verified', $route->gatherMiddleware(), $name);
            $this->assertContains('admin', $route->gatherMiddleware(), $name);
        }

        $setupApplyRoute = RouteFacade::getRoutes()->getByName('app.admin.boxes.setup.apply');
        $this->assertNotNull($setupApplyRoute);
        $this->assertContains('auth', $setupApplyRoute->gatherMiddleware());
        $this->assertContains('verified', $setupApplyRoute->gatherMiddleware());
        $this->assertContains('admin', $setupApplyRoute->gatherMiddleware());
        $this->assertContains('throttle:12,1', $setupApplyRoute->gatherMiddleware());

        $billingChargeRoute = RouteFacade::getRoutes()->getByName('app.admin.billing.charge');
        $this->assertNotNull($billingChargeRoute);
        $this->assertContains('auth', $billingChargeRoute->gatherMiddleware());
        $this->assertContains('verified', $billingChargeRoute->gatherMiddleware());
        $this->assertContains('admin', $billingChargeRoute->gatherMiddleware());
        $this->assertContains('throttle:12,1', $billingChargeRoute->gatherMiddleware());
    }

    public function test_public_home_route_uses_coming_soon_controller(): void
    {
        $homeRoute = RouteFacade::getRoutes()->getByName('public.home');

        $this->assertNotNull($homeRoute);
        $this->assertStringContainsString(
            ComingSoonController::class,
            (string) $homeRoute->getAction('controller')
        );
    }

    public function test_public_marketing_home_route_uses_marketing_home_controller(): void
    {
        $homeRoute = RouteFacade::getRoutes()->getByName('public.marketing-home');

        $this->assertNotNull($homeRoute);
        $this->assertSame([HomeController::class, 'index'], $homeRoute->getAction('controller')
            ? explode('@', $homeRoute->getAction('controller'))
            : []);
    }

    public function test_app_dashboard_route_uses_dashboard_controller(): void
    {
        $dashboardRoute = RouteFacade::getRoutes()->getByName('app.dashboard');

        $this->assertNotNull($dashboardRoute);
        $this->assertSame([DashboardController::class, 'index'], $dashboardRoute->getAction('controller')
            ? explode('@', $dashboardRoute->getAction('controller'))
            : []);
    }

    public function test_app_admin_dashboard_route_uses_admin_dashboard_controller(): void
    {
        $adminDashboardRoute = RouteFacade::getRoutes()->getByName('app.admin.dashboard');

        $this->assertNotNull($adminDashboardRoute);
        $this->assertSame([AdminDashboardController::class, 'index'], $adminDashboardRoute->getAction('controller')
            ? explode('@', $adminDashboardRoute->getAction('controller'))
            : []);
    }

    public function test_app_admin_operations_indexes_use_expected_controllers(): void
    {
        $map = [
            'app.admin.users.index' => [AdminUserController::class, 'index'],
            'app.admin.subscriptions.index' => [AdminSubscriptionController::class, 'index'],
            'app.admin.deliveries.sync-from-boxes' => [AdminDeliveryController::class, 'syncFromBoxes'],
            'app.admin.deliveries.create' => [AdminDeliveryController::class, 'create'],
            'app.admin.deliveries.store' => [AdminDeliveryController::class, 'store'],
            'app.admin.deliveries.index' => [AdminDeliveryController::class, 'index'],
            'app.admin.deliveries.edit' => [AdminDeliveryController::class, 'edit'],
            'app.admin.deliveries.update' => [AdminDeliveryController::class, 'update'],
            'app.admin.boxes.index' => [AdminBoxSetupController::class, 'index'],
            'app.admin.boxes.setup.show' => [AdminBoxSetupController::class, 'show'],
            'app.admin.boxes.setup.apply' => [AdminBoxSetupController::class, 'apply'],
            'app.admin.boxes.week.show' => [AdminBoxSetupController::class, 'weekBoxes'],
            'app.admin.boxes.items.store' => [AdminBoxItemController::class, 'store'],
            'app.admin.boxes.items.update' => [AdminBoxItemController::class, 'update'],
            'app.admin.boxes.items.destroy' => [AdminBoxItemController::class, 'destroy'],
            'app.admin.boxes.show' => [AdminBoxController::class, 'show'],
            'app.admin.payments.index' => [AdminPaymentController::class, 'index'],
            'app.admin.billing.upcoming' => [AdminUpcomingChargeController::class, 'index'],
            'app.admin.billing.coverage' => [AdminBillingCoverageController::class, 'index'],
            'app.admin.billing.charge' => [AdminBillingCoverageController::class, 'charge'],
        ];

        foreach ($map as $name => $expected) {
            $route = RouteFacade::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertSame($expected, $route->getAction('controller')
                ? explode('@', $route->getAction('controller'))
                : []);
        }
    }

    public function test_app_boxes_and_deliveries_routes_use_expected_controllers(): void
    {
        $boxCurrentRoute = RouteFacade::getRoutes()->getByName('app.boxes.current');
        $boxRoute = RouteFacade::getRoutes()->getByName('app.boxes.show');
        $deliveryShowRoute = RouteFacade::getRoutes()->getByName('app.deliveries.show');
        $deliveryGiftRoute = RouteFacade::getRoutes()->getByName('app.deliveries.gift');
        $deliveryPreferencesEditRoute = RouteFacade::getRoutes()->getByName('app.deliveries.preferences.edit');
        $deliveryPreferencesUpdateRoute = RouteFacade::getRoutes()->getByName('app.deliveries.preferences.update');

        $this->assertNotNull($boxCurrentRoute);
        $this->assertNotNull($boxRoute);
        $this->assertNotNull($deliveryShowRoute);
        $this->assertNotNull($deliveryGiftRoute);
        $this->assertNotNull($deliveryPreferencesEditRoute);
        $this->assertNotNull($deliveryPreferencesUpdateRoute);

        $this->assertSame([BoxController::class, 'current'], $boxCurrentRoute->getAction('controller')
            ? explode('@', $boxCurrentRoute->getAction('controller'))
            : []);
        $this->assertSame([BoxController::class, 'show'], $boxRoute->getAction('controller')
            ? explode('@', $boxRoute->getAction('controller'))
            : []);

        $this->assertSame([DeliveryController::class, 'show'], $deliveryShowRoute->getAction('controller')
            ? explode('@', $deliveryShowRoute->getAction('controller'))
            : []);
        $this->assertSame([DeliveryController::class, 'gift'], $deliveryGiftRoute->getAction('controller')
            ? explode('@', $deliveryGiftRoute->getAction('controller'))
            : []);
        $this->assertSame([DeliveryPreferencesController::class, 'edit'], $deliveryPreferencesEditRoute->getAction('controller')
            ? explode('@', $deliveryPreferencesEditRoute->getAction('controller'))
            : []);
        $this->assertSame([DeliveryPreferencesController::class, 'update'], $deliveryPreferencesUpdateRoute->getAction('controller')
            ? explode('@', $deliveryPreferencesUpdateRoute->getAction('controller'))
            : []);

        // Removed customer-side mutation routes
        $this->assertFalse(RouteFacade::has('app.boxes.items.add'));
        $this->assertFalse(RouteFacade::has('app.boxes.items.updateQuantity'));
        $this->assertFalse(RouteFacade::has('app.boxes.items.remove'));
        $this->assertFalse(RouteFacade::has('app.deliveries.skip'));
        $this->assertFalse(RouteFacade::has('app.deliveries.postpone'));
        $this->assertFalse(RouteFacade::has('app.deliveries.override.update'));
        $this->assertFalse(RouteFacade::has('app.shop.index'));
        $this->assertFalse(RouteFacade::has('app.shop.show'));
    }

    public function test_products_catalog_routes_use_product_controller(): void
    {
        $indexRoute = RouteFacade::getRoutes()->getByName('app.products.index');
        $showRoute = RouteFacade::getRoutes()->getByName('app.products.show');

        $this->assertNotNull($indexRoute);
        $this->assertNotNull($showRoute);

        $this->assertSame([ProductController::class, 'index'], $indexRoute->getAction('controller')
            ? explode('@', $indexRoute->getAction('controller'))
            : []);
        $this->assertSame([ProductController::class, 'show'], $showRoute->getAction('controller')
            ? explode('@', $showRoute->getAction('controller'))
            : []);

        $this->assertContains('auth', $indexRoute->gatherMiddleware());
        $this->assertContains('verified', $indexRoute->gatherMiddleware());
    }

    public function test_admin_delivery_status_update_route_wires_controller_and_throttle(): void
    {
        $route = RouteFacade::getRoutes()->getByName('app.admin.deliveries.status.update');
        $this->assertNotNull($route);
        $this->assertSame([AdminDeliveryController::class, 'updateStatus'], $route->getAction('controller')
            ? explode('@', $route->getAction('controller'))
            : []);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('verified', $route->gatherMiddleware());
        $this->assertContains('admin', $route->gatherMiddleware());
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());
    }

    public function test_subscription_skip_next_route_uses_subscription_controller(): void
    {
        $route = RouteFacade::getRoutes()->getByName('app.subscriptions.skip.next');
        $this->assertNotNull($route);
        $this->assertSame([SubscriptionController::class, 'skipNextWeek'], $route->getAction('controller')
            ? explode('@', $route->getAction('controller'))
            : []);
    }

    public function test_app_subscriptions_route_uses_subscription_controller(): void
    {
        $subscriptionRoute = RouteFacade::getRoutes()->getByName('app.subscriptions.show');
        $subscriptionEditRoute = RouteFacade::getRoutes()->getByName('app.subscriptions.edit');
        $subscriptionUpdateRoute = RouteFacade::getRoutes()->getByName('app.subscriptions.update');
        $subscriptionPauseRoute = RouteFacade::getRoutes()->getByName('app.subscriptions.pause');
        $subscriptionResumeRoute = RouteFacade::getRoutes()->getByName('app.subscriptions.resume');
        $subscriptionCancelRoute = RouteFacade::getRoutes()->getByName('app.subscriptions.cancel');

        $this->assertNotNull($subscriptionRoute);
        $this->assertNotNull($subscriptionEditRoute);
        $this->assertNotNull($subscriptionUpdateRoute);
        $this->assertNotNull($subscriptionPauseRoute);
        $this->assertNotNull($subscriptionResumeRoute);
        $this->assertNotNull($subscriptionCancelRoute);
        $this->assertSame([SubscriptionController::class, 'show'], $subscriptionRoute->getAction('controller')
            ? explode('@', $subscriptionRoute->getAction('controller'))
            : []);
        $this->assertSame([SubscriptionController::class, 'edit'], $subscriptionEditRoute->getAction('controller')
            ? explode('@', $subscriptionEditRoute->getAction('controller'))
            : []);
        $this->assertSame([SubscriptionController::class, 'update'], $subscriptionUpdateRoute->getAction('controller')
            ? explode('@', $subscriptionUpdateRoute->getAction('controller'))
            : []);
        $this->assertSame([SubscriptionController::class, 'pause'], $subscriptionPauseRoute->getAction('controller')
            ? explode('@', $subscriptionPauseRoute->getAction('controller'))
            : []);
        $this->assertSame([SubscriptionController::class, 'resume'], $subscriptionResumeRoute->getAction('controller')
            ? explode('@', $subscriptionResumeRoute->getAction('controller'))
            : []);
        $this->assertSame([SubscriptionController::class, 'cancel'], $subscriptionCancelRoute->getAction('controller')
            ? explode('@', $subscriptionCancelRoute->getAction('controller'))
            : []);

        $onboardStep1Route = RouteFacade::getRoutes()->getByName('app.subscriptions.onboard');
        $onboardStoreRoute = RouteFacade::getRoutes()->getByName('app.subscriptions.onboard.store');
        $onboardPaymentRoute = RouteFacade::getRoutes()->getByName('app.subscriptions.onboard.payment');
        $onboardCompleteRoute = RouteFacade::getRoutes()->getByName('app.subscriptions.onboard.complete');

        $this->assertNotNull($onboardStep1Route);
        $this->assertNotNull($onboardStoreRoute);
        $this->assertNotNull($onboardPaymentRoute);
        $this->assertNotNull($onboardCompleteRoute);
        foreach ([$onboardStep1Route, $onboardStoreRoute, $onboardPaymentRoute, $onboardCompleteRoute] as $legacyOnboardRoute) {
            $this->assertInstanceOf(\Closure::class, $legacyOnboardRoute->getAction('uses'));
        }

        $deliveriesIndexRoute = RouteFacade::getRoutes()->getByName('app.deliveries.index');
        $this->assertNotNull($deliveriesIndexRoute);
        $this->assertContains('subscriber.full', $deliveriesIndexRoute->gatherMiddleware());
    }

    public function test_public_about_route_uses_marketing_about_controller(): void
    {
        $aboutRoute = RouteFacade::getRoutes()->getByName('public.about');

        $this->assertNotNull($aboutRoute);
        $this->assertSame([AboutController::class, 'index'], $aboutRoute->getAction('controller')
            ? explode('@', $aboutRoute->getAction('controller'))
            : []);
    }

    public function test_public_producers_route_uses_marketing_producer_controller(): void
    {
        $producersRoute = RouteFacade::getRoutes()->getByName('public.producers');

        $this->assertNotNull($producersRoute);
        $this->assertSame([ProducerController::class, 'index'], $producersRoute->getAction('controller')
            ? explode('@', $producersRoute->getAction('controller'))
            : []);
    }

    public function test_public_contact_routes_use_marketing_contact_controller(): void
    {
        $contactCreateRoute = RouteFacade::getRoutes()->getByName('public.contact');
        $contactStoreRoute = RouteFacade::getRoutes()->getByName('public.contact.store');

        $this->assertNotNull($contactCreateRoute);
        $this->assertNotNull($contactStoreRoute);

        $this->assertSame([ContactController::class, 'create'], $contactCreateRoute->getAction('controller')
            ? explode('@', $contactCreateRoute->getAction('controller'))
            : []);
        $this->assertSame([ContactController::class, 'store'], $contactStoreRoute->getAction('controller')
            ? explode('@', $contactStoreRoute->getAction('controller'))
            : []);
    }
}
