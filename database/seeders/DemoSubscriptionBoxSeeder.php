<?php

namespace Database\Seeders;

use App\Enums\BoxStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\BoxItem;
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
            $subscription = Subscription::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'plan' => SubscriptionPlan::Weekly,
                    'box_size' => 'medium',
                    'preferences' => ['veg_only' => false, 'meat_add_on' => false],
                    'allergies' => [],
                    'status' => SubscriptionStatus::Active,
                    'is_test' => true,
                ]
            );

            $this->createBoxesForUser($user, $subscription, $productIds);
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

    /**
     * @param  array<int>  $productIds
     */
    private function createBoxesForUser(User $user, Subscription $subscription, array $productIds): void
    {
        $baseDate = now()->startOfWeek();

        $boxes = [
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
}
