<?php

namespace Tests\Feature\Admin;

use App\Models\CatalogAuditLog;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_product_create_does_not_write_audit_without_admin(): void
    {
        Product::factory()->create();

        $this->assertSame(0, CatalogAuditLog::query()->count());
    }

    public function test_admin_product_store_update_destroy_writes_audit_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'name' => 'Audit Apple',
                'category' => 'fruit',
                'unit_price' => '1.00',
            ])
            ->assertRedirect();

        $product = Product::query()->where('name', 'Audit Apple')->firstOrFail();

        $this->assertDatabaseCount('catalog_audit_logs', 1);
        $this->assertDatabaseHas('catalog_audit_logs', [
            'user_id' => $admin->id,
            'auditable_type' => $product->getMorphClass(),
            'auditable_id' => $product->id,
            'action' => 'created',
        ]);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'name' => 'Audit Apple Renamed',
                'category' => 'fruit',
                'unit_price' => '2.00',
                'is_available' => '1',
                'is_published' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('catalog_audit_logs', 2);
        $this->assertTrue(
            CatalogAuditLog::query()->where('action', 'updated')->where('auditable_id', $product->id)->exists()
        );

        $this->actingAs($admin)
            ->from(route('app.admin.products.index'))
            ->delete(route('app.admin.products.destroy', $product->fresh()))
            ->assertRedirect(route('app.admin.products.index'));

        $this->assertDatabaseCount('catalog_audit_logs', 3);
        $this->assertTrue(
            CatalogAuditLog::query()->where('action', 'deleted')->where('auditable_id', $product->id)->exists()
        );
    }

    public function test_admin_producer_store_and_delete_writes_audit_rows(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.producers.store'), [
                'name' => 'Audit Farm',
            ])
            ->assertRedirect();

        $producer = Producer::query()->where('name', 'Audit Farm')->firstOrFail();

        $this->assertTrue(
            CatalogAuditLog::query()->where('action', 'created')->where('auditable_id', $producer->id)->exists()
        );

        $this->actingAs($admin)
            ->from(route('app.admin.producers.index'))
            ->delete(route('app.admin.producers.destroy', $producer))
            ->assertRedirect(route('app.admin.producers.index'));

        $this->assertTrue(
            CatalogAuditLog::query()->where('action', 'deleted')->where('auditable_id', $producer->id)->exists()
        );
    }
}
