<?php

namespace Database\Seeders;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Delivery;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BoxValueRecalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSubscriptionBoxSeeder extends Seeder
{
    /**
     * Seed demo subscriptions and boxes for QA and UI preview.
     * Requires ProducerSeeder and ProductSeeder to run first.
     */
    public function run(): void
    {
        $products = Product::query()->where('is_test', true)->get();
        if ($products->isEmpty()) {
            return;
        }

        $demoUsers = $this->createDemoUsers();
        $productIds = $products->pluck('id')->all();

        foreach ($demoUsers as $user) {
            $address = $this->createAddressForUser($user);

            $subscription = Subscription::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'plan' => SubscriptionPlan::Weekly,
                    'box_size' => 'medium',
                    'preferences' => ['veg_only' => false, 'meat_add_on' => false, 'eggs_dairy_add_on' => false],
                    'allergies' => [],
                    'status' => SubscriptionStatus::Active,
                    // Demo subs are billed for real-looking dashboards. Without
                    // a token the state resolver treats Active+no_token as
                    // PendingPayment (legacy data shape) and hides subscription
                    // box panels — making the seeded boxes invisible. The
                    // placeholder token mirrors the SubscriptionFactory's
                    // withPaymentToken() state.
                    'sumup_token' => 'demo-token-'.$user->id,
                    'sumup_customer_id' => 'demo-customer-'.$user->id,
                    'is_test' => true,
                    'trial_completed_at' => now(),
                ]
            );

            $this->createBoxesForUser($user, $subscription, $productIds);
            $this->createDeliveriesForUser($user, $address);
        }
    }

    /**
     * @return array<int, User>
     */
    private function createDemoUsers(): array
    {
        $users = [
            [
                'name' => 'Demo QA User',
                'email' => 'demo-qa@erdgut.local',
            ],
            [
                'name' => 'Demo UI User',
                'email' => 'demo-ui@erdgut.local',
            ],
        ];

        $created = [];
        foreach ($users as $data) {
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_test' => true,
                ]
            );
            $created[] = $user;
        }

        return $created;
    }

    private function createAddressForUser(User $user): Address
    {
        return Address::query()->updateOrCreate(
            ['user_id' => $user->id, 'label' => 'Home'],
            [
                'recipient_name' => $user->name,
                'street_line_1' => 'Demo Street 1',
                'postal_code' => '10115',
                'city' => 'Berlin',
                'country_code' => 'DE',
                'is_primary' => true,
                'is_test' => true,
            ]
        );
    }

    /**
     * @param  array<int>  $productIds
     */
    private function createBoxesForUser(User $user, Subscription $subscription, array $productIds): void
    {
        $baseDate = now()->startOfWeek();

        $boxes = [
            [
                'week_start' => $baseDate->copy(),
                'status' => BoxStatus::Editable,
            ],
            [
                'week_start' => $baseDate->copy()->addWeek(),
                'status' => BoxStatus::Editable,
            ],
            [
                'week_start' => $baseDate->copy()->addWeeks(2),
                'status' => BoxStatus::Locked,
            ],
            [
                'week_start' => $baseDate->copy()->subWeek(),
                'status' => BoxStatus::Delivered,
            ],
        ];

        foreach ($boxes as $boxData) {
            $weekStart = $boxData['week_start'];
            $modificationDeadline = $weekStart->copy()->subDays(2)->setTime(14, 0, 0);

            $box = Box::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'week_start_date' => $weekStart->format('Y-m-d'),
                ],
                [
                    'subscription_id' => $subscription->id,
                    'status' => $boxData['status'],
                    'modification_deadline_at' => $modificationDeadline,
                    'estimated_value' => 0,
                    'is_test' => true,
                ]
            );

            $this->createBoxItems($box, $productIds);
        }
    }

    /**
     * @param  array<int>  $productIds
     */
    private function createBoxItems(Box $box, array $productIds): void
    {
        $selected = collect($productIds)->shuffle()->take(min(5, count($productIds)))->all();

        $quantity = 1;
        foreach ($selected as $productId) {
            BoxItem::query()->updateOrCreate(
                [
                    'box_id' => $box->id,
                    'product_id' => $productId,
                ],
                [
                    'quantity' => $quantity,
                    'unit' => 'kg',
                    'is_extra' => false,
                    'is_test' => true,
                ]
            );
            $quantity++;
        }

        app(BoxValueRecalculationService::class)->recalculate($box);
    }

    private function createDeliveriesForUser(User $user, Address $address): void
    {
        $baseDate = now()->startOfWeek();
        $timeslot = '08:00-12:00';

        $deliveryDates = [
            $baseDate->copy()->addDays(2),
            $baseDate->copy()->addWeeks(1)->addDays(2),
            $baseDate->copy()->addWeeks(2)->addDays(2),
        ];

        foreach ($deliveryDates as $scheduledFor) {
            Delivery::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'scheduled_for_date' => $scheduledFor->format('Y-m-d'),
                    'is_weekly_override' => false,
                ],
                [
                    'address_id' => $address->id,
                    'timeslot' => $timeslot,
                    'status' => DeliveryStatus::Scheduled,
                    'is_test' => true,
                ]
            );
        }
    }
}
