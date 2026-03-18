<?php

namespace Tests\Feature\Database;

use App\Models\Address;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BoxItemModelRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_box_item_can_be_attached_to_box_and_product(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $box = Box::factory()->create(['user_id' => $user->id]);
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id]);

        $item = BoxItem::create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'piece',
            'is_extra' => true,
        ]);

        $this->assertDatabaseHas('box_items', [
            'id' => $item->id,
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'piece',
            'is_extra' => 1,
        ]);

        $this->assertSame($box->id, $item->box->id);
        $this->assertSame($product->id, $item->product->id);
    }

    public function test_box_item_quantity_must_be_greater_than_zero(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $box = Box::factory()->create(['user_id' => $user->id]);
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Box item quantity must be greater than zero.');

        BoxItem::create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 0,
            'unit' => 'kg',
            'is_extra' => false,
        ]);
    }

    public function test_relationship_contracts_are_exposed(): void
    {
        $box = new Box;
        $product = new Product;
        $boxItem = new BoxItem;

        $this->assertInstanceOf(HasMany::class, $box->items());
        $this->assertInstanceOf(HasMany::class, $product->boxItems());
        $this->assertInstanceOf(BelongsTo::class, $boxItem->box());
        $this->assertInstanceOf(BelongsTo::class, $boxItem->product());
    }
}
