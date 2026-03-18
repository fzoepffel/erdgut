<?php

namespace Tests\Feature\Database;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressModelCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_multiple_addresses(): void
    {
        $user = User::factory()->create();

        Address::factory()->create(['user_id' => $user->id, 'label' => 'Home']);
        Address::factory()->create(['user_id' => $user->id, 'label' => 'Work']);

        $this->assertCount(2, $user->fresh()->addresses);
        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'label' => 'Home',
        ]);
        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'label' => 'Work',
        ]);
    }

    public function test_user_can_mark_one_address_as_primary(): void
    {
        $user = User::factory()->create();

        Address::factory()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'is_primary' => false,
        ]);
        $primaryAddress = Address::factory()->create([
            'user_id' => $user->id,
            'label' => 'Work',
            'is_primary' => true,
        ]);

        $this->assertNotNull($user->fresh()->primaryAddress);
        $this->assertSame($primaryAddress->id, $user->fresh()->primaryAddress?->id);
    }

    public function test_user_address_relationship_contracts_are_exposed(): void
    {
        $user = new User;

        $this->assertInstanceOf(HasMany::class, $user->addresses());
        $this->assertInstanceOf(HasOne::class, $user->primaryAddress());
    }
}
