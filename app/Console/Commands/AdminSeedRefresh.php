<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Delivery;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DemoSubscriptionBoxSeeder;
use Database\Seeders\ProducerSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AdminSeedRefresh extends Command
{
    protected $signature = 'admin:seed-refresh
                            {--wipe : Delete existing test data (is_test=true) before seeding}';

    protected $description = 'Refresh staging/test seed data (ProducerSeeder, ProductSeeder, DemoSubscriptionBoxSeeder). Only runs when APP_ENV !== production.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('This command cannot run in production.');

            return self::FAILURE;
        }

        if ($this->option('wipe')) {
            $this->wipeTestData();
        }

        Artisan::call('db:seed', ['--class' => ProducerSeeder::class]);
        Artisan::call('db:seed', ['--class' => ProductSeeder::class]);
        Artisan::call('db:seed', ['--class' => DemoSubscriptionBoxSeeder::class]);

        $this->info('Seed refresh complete.');

        return self::SUCCESS;
    }

    private function wipeTestData(): void
    {
        $deleted = 0;

        $testUserIds = User::query()->where('is_test', true)->pluck('id');
        if ($testUserIds->isNotEmpty()) {
            BoxItem::query()->whereIn('box_id', Box::query()->whereIn('user_id', $testUserIds)->pluck('id'))->delete();
            Box::query()->whereIn('user_id', $testUserIds)->delete();
            Delivery::query()->where('user_id', $testUserIds)->where('is_weekly_override', true)->delete();
            Delivery::query()->whereIn('user_id', $testUserIds)->delete();
            Subscription::query()->whereIn('user_id', $testUserIds)->delete();
            Address::query()->whereIn('user_id', $testUserIds)->delete();
            $deleted += User::query()->whereIn('id', $testUserIds)->delete();
        }

        $deleted += Product::query()->where('is_test', true)->delete();
        $deleted += Producer::query()->where('is_test', true)->delete();

        $this->info("Wiped {$deleted} test record(s).");
    }
}
