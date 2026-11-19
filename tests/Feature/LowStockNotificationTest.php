<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\MidtransService;
use App\Services\NotificationService;
use App\Services\TransactionService;
use Database\Factories\ProductFactory;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class LowStockNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminInventory;

    private User $member;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        config(['floristsync.low_stock_threshold' => 5]);

        $inventoryRole = Role::where('role_name', Role::ADMIN_INVENTORY)->firstOrFail();
        $memberRole = Role::where('role_name', Role::MEMBER)->firstOrFail();

        $this->adminInventory = User::factory()->create([
            'role_id' => $inventoryRole->id,
            'is_active' => true,
        ]);

        $this->member = User::factory()->create([
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        $category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'product_name' => 'Sunflower Delight',
            'price' => 50000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test')->byDefault();
            $mock->shouldReceive('cancelInvoice')->andReturnNull()->byDefault();
            $mock->shouldReceive('getStatus')->andReturn(null)->byDefault();
        });
    }

    public function test_two_sales_in_a_row_while_first_notification_is_unread_does_not_duplicate(): void
    {
        ProductFactory::setStock($this->product, 6);

        $inventory = app(InventoryService::class);

        // First sale drops stock to 4 (below threshold 5) -> triggers first notification
        $inventory->reduce($this->product, 2);

        $this->assertSame(4, $this->product->fresh()->stock);
        $this->assertSame(1, AppNotification::where('user_id', $this->adminInventory->id)->where('type', 'low_stock')->count());

        // Second sale drops stock to 3 while the first notification is still unread
        $inventory->reduce($this->product, 1);

        $this->assertSame(3, $this->product->fresh()->stock);
        $this->assertSame(
            1,
            AppNotification::where('user_id', $this->adminInventory->id)->where('type', 'low_stock')->where('is_read', false)->count(),
            'Should not create duplicate unread low stock notification on consecutive sales'
        );
    }

    public function test_one_sale_with_same_product_on_two_lines_does_not_duplicate(): void
    {
        ProductFactory::setStock($this->product, 6);

        $transactionService = app(TransactionService::class);

        $items = [
            ['product_id' => $this->product->id, 'qty' => 1],
            ['product_id' => $this->product->id, 'qty' => 1],
        ];

        $transactionService->createOnlineTransaction(
            $items,
            ['name' => 'Buyer', 'phone' => '081234567890'],
            ['method' => 'pickup'],
            $this->member->id
        );

        $this->assertSame(4, $this->product->fresh()->stock);
        $this->assertSame(
            1,
            AppNotification::where('user_id', $this->adminInventory->id)->where('type', 'low_stock')->count(),
            'Sale with same product on multiple lines should trigger only 1 low stock notification'
        );
    }

    public function test_stock_above_threshold_does_not_create_notification(): void
    {
        ProductFactory::setStock($this->product, 10);

        app(InventoryService::class)->reduce($this->product, 2);

        $this->assertSame(8, $this->product->fresh()->stock);
        $this->assertSame(
            0,
            AppNotification::where('user_id', $this->adminInventory->id)->where('type', 'low_stock')->count(),
            'Stock remaining above threshold should not trigger any low stock notification'
        );
    }

    public function test_read_notification_allows_new_notification_after_restock_and_fall(): void
    {
        ProductFactory::setStock($this->product, 6);

        $inventory = app(InventoryService::class);

        // Fall below threshold -> 1st notification
        $inventory->reduce($this->product, 2);
        $this->assertSame(4, $this->product->fresh()->stock);

        $firstNotification = AppNotification::where('user_id', $this->adminInventory->id)
            ->where('type', 'low_stock')
            ->firstOrFail();

        // Mark as read
        $firstNotification->update(['is_read' => true]);

        // Restock above limit
        $inventory->add($this->product, 10);
        $this->assertSame(14, $this->product->fresh()->stock);

        // Falls below limit again
        $inventory->reduce($this->product, 10);
        $this->assertSame(4, $this->product->fresh()->stock);

        $this->assertSame(
            2,
            AppNotification::where('user_id', $this->adminInventory->id)->where('type', 'low_stock')->count(),
            'Should create a new notification when restocked and fallen again after previous was read'
        );

        $this->assertSame(
            1,
            AppNotification::where('user_id', $this->adminInventory->id)->where('type', 'low_stock')->where('is_read', false)->count()
        );
    }

    public function test_product_name_changed_between_notifications_does_not_duplicate_when_first_unread(): void
    {
        ProductFactory::setStock($this->product, 6);

        $inventory = app(InventoryService::class);

        // Fall below threshold -> 1st notification with original name
        $inventory->reduce($this->product, 2);
        $this->assertSame(4, $this->product->fresh()->stock);

        $unreadCount = AppNotification::where('user_id', $this->adminInventory->id)
            ->where('type', 'low_stock')
            ->where('is_read', false)
            ->count();
        $this->assertSame(1, $unreadCount);

        // Product name is renamed
        $this->product->update(['product_name' => 'Sunflower Golden Premium']);

        // Another sale occurs while the first notification is still unread
        $inventory->reduce($this->product, 1);
        $this->assertSame(3, $this->product->fresh()->stock);

        $this->assertSame(
            1,
            AppNotification::where('user_id', $this->adminInventory->id)->where('type', 'low_stock')->where('is_read', false)->count(),
            'Should not duplicate unread notification even when product name was changed'
        );
    }

    public function test_inactive_product_does_not_create_notification(): void
    {
        $this->product->update(['is_active' => false]);
        ProductFactory::setStock($this->product, 3);

        $current = Product::withStock()->find($this->product->id);
        app(NotificationService::class)->notifyLowStock($current);

        $this->assertSame(
            0,
            AppNotification::where('user_id', $this->adminInventory->id)->where('type', 'low_stock')->count(),
            'Inactive product should not trigger low stock notification'
        );
    }
}

