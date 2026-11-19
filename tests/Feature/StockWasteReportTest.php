<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ReportService;
use Database\Factories\ProductFactory;
use Database\Seeders\RolesMenusSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockWasteReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $inventoryAdmin;

    private Category $category;

    private Product $rose;

    private Product $tulip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $ownerRole = Role::where('role_name', Role::OWNER)->firstOrFail();
        $invRole = Role::where('role_name', Role::ADMIN_INVENTORY)->firstOrFail();

        $this->owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'is_active' => true,
        ]);

        $this->inventoryAdmin = User::factory()->create([
            'role_id' => $invRole->id,
            'is_active' => true,
        ]);

        $this->category = Category::factory()->create(['category_name' => 'Fresh Cut Flowers']);

        $this->rose = Product::factory()->create([
            'category_id' => $this->category->id,
            'product_name' => 'Red Rose Bouquet',
            'price' => 50000,
            'stock' => 20,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->tulip = Product::factory()->create([
            'category_id' => $this->category->id,
            'product_name' => 'Yellow Tulip Stems',
            'price' => 30000,
            'stock' => 20,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    public function test_negative_adjustment_without_reason_is_refused_by_service(): void
    {
        ProductFactory::setStock($this->rose, 10);

        try {
            app(InventoryService::class)->adjust($this->rose, -2, 'Wilted flower without reason');
            $this->fail('Expected DomainException was not thrown for negative adjustment without reason.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('reason is required', strtolower($e->getMessage()));
        }

        $this->assertSame(10, $this->rose->fresh()->stock);
    }

    public function test_negative_adjustment_without_reason_is_refused_by_controller(): void
    {
        ProductFactory::setStock($this->rose, 10);

        $response = $this->actingAs($this->inventoryAdmin)
            ->post(route('admin.stock.adjust.store'), [
                'product_id' => $this->rose->id,
                'change' => -3,
                'notes' => 'Damaged flowers',
                // reason omitted
            ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(10, $this->rose->fresh()->stock);
    }

    public function test_negative_adjustment_with_valid_reason_is_saved_and_shown_in_history(): void
    {
        ProductFactory::setStock($this->rose, 10);

        $response = $this->actingAs($this->inventoryAdmin)
            ->post(route('admin.stock.adjust.store'), [
                'product_id' => $this->rose->id,
                'change' => -2,
                'reason' => Stock::REASON_WILTED,
                'notes' => 'Petals browned',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertSame(8, $this->rose->fresh()->stock);

        $stockEntry = Stock::where('product_id', $this->rose->id)
            ->where('change_type', 'adjustment')
            ->firstOrFail();

        $this->assertSame(-2, $stockEntry->quantity_change);
        $this->assertSame(Stock::REASON_WILTED, $stockEntry->reason);
        $this->assertSame('Petals browned', $stockEntry->notes);

        // Verify reason is displayed on stock history page
        $historyResponse = $this->actingAs($this->inventoryAdmin)
            ->get(route('admin.stock.history'));

        $historyResponse->assertOk();
        $historyResponse->assertSee('Wilted');
        $historyResponse->assertSee('Petals browned');
    }

    public function test_positive_adjustment_does_not_require_reason(): void
    {
        ProductFactory::setStock($this->rose, 10);

        $response = $this->actingAs($this->inventoryAdmin)
            ->post(route('admin.stock.adjust.store'), [
                'product_id' => $this->rose->id,
                'change' => 5,
                'notes' => 'Found extra stems',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(15, $this->rose->fresh()->stock);
    }

    public function test_stock_never_goes_below_zero_on_adjustment(): void
    {
        ProductFactory::setStock($this->rose, 3);

        try {
            app(InventoryService::class)->adjust($this->rose, -5, 'Cannot remove more than exists', Stock::REASON_WILTED);
            $this->fail('Expected DomainException was not thrown when stock would go below zero.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('cannot go below zero', $e->getMessage());
        }

        $this->assertSame(3, $this->rose->fresh()->stock);

        // Also test controller level
        $response = $this->actingAs($this->inventoryAdmin)
            ->post(route('admin.stock.adjust.store'), [
                'product_id' => $this->rose->id,
                'change' => -10,
                'reason' => Stock::REASON_DAMAGED,
                'notes' => 'Too much reduction',
            ]);

        $response->assertSessionHas('error');
        $this->assertSame(3, $this->rose->fresh()->stock);
    }

    public function test_waste_report_totals_match_stock_rows_and_ignore_non_waste_movements(): void
    {
        ProductFactory::setStock($this->rose, 30);
        ProductFactory::setStock($this->tulip, 30);

        $inventory = app(InventoryService::class);

        // Waste movements (should count):
        // Rose: -3 wilted (3 * 50.000 = 150.000)
        $inventory->adjust($this->rose, -3, 'Wilted petals', Stock::REASON_WILTED);
        // Rose: -2 damaged (2 * 50.000 = 100.000)
        $inventory->adjust($this->rose, -2, 'Stem broken during transport', Stock::REASON_DAMAGED);
        // Tulip: -4 wilted (4 * 30.000 = 120.000)
        $inventory->adjust($this->tulip, -4, 'Wilted heads', Stock::REASON_WILTED);

        // Non-waste movements (MUST NOT COUNT AS WASTE):
        // 1. Gift / sample adjustment
        $inventory->adjust($this->tulip, -2, 'Sample given to wedding planner', Stock::REASON_GIFT_SAMPLE);
        // 2. Miscount adjustment
        $inventory->adjust($this->rose, -1, 'Audit count correction', Stock::REASON_MISCOUNT);
        // 3. Normal sale reduction (change_type = 'out')
        $inventory->reduce($this->rose, 5, 'POS Sale');
        // 4. Restock (change_type = 'in')
        $inventory->add($this->tulip, 10, 'in', 'New arrival');
        // 5. Cancelled sale stock log (change_type = 'cancel')
        Stock::create([
            'product_id' => $this->rose->id,
            'quantity_change' => 2,
            'change_type' => 'cancel',
            'notes' => 'Sale cancelled #123',
            'date' => now(),
        ]);

        $reports = app(ReportService::class);
        $from = now()->startOfMonth();
        $to = now()->endOfDay();

        $wasteData = $reports->waste($from, $to);

        // Should only have 2 product entries in waste: Rose and Tulip
        $this->assertCount(2, $wasteData);

        $roseWaste = $wasteData->firstWhere('id', $this->rose->id);
        $this->assertNotNull($roseWaste);
        $this->assertSame(5, (int) $roseWaste->quantity_lost); // 3 + 2
        $this->assertSame(250000, (int) $roseWaste->value_lost); // 5 * 50.000

        $tulipWaste = $wasteData->firstWhere('id', $this->tulip->id);
        $this->assertNotNull($tulipWaste);
        $this->assertSame(4, (int) $tulipWaste->quantity_lost); // only the 4 wilted
        $this->assertSame(120000, (int) $tulipWaste->value_lost); // 4 * 30.000

        // Test summary cards
        $query = $reports->query('waste', ['from' => $from, 'to' => $to]);
        $cards = $reports->cards('waste', ['from' => $from, 'to' => $to], $query);

        $this->assertSame('2', $cards[0]['value']); // Products with waste
        $this->assertSame('9', $cards[1]['value']); // Total units lost (5 + 4)
        $this->assertSame(rupiah(370000), $cards[2]['value']); // Total value lost (250.000 + 120.000)

        // Web UI view
        $response = $this->actingAs($this->owner)
            ->get(route('admin.reports', ['tab' => 'waste']));

        $response->assertOk();
        $response->assertSee('Waste');
        $response->assertSee('Units Lost');
        $response->assertSee('Value Lost');
        $response->assertSee('Red Rose Bouquet');
        $response->assertSee('Yellow Tulip Stems');
        $response->assertSee(rupiah(370000));
    }

    public function test_waste_report_can_be_exported_to_pdf_and_excel(): void
    {
        ProductFactory::setStock($this->rose, 20);
        app(InventoryService::class)->adjust($this->rose, -2, 'Wilted flower', Stock::REASON_WILTED);

        $pdfResponse = $this->actingAs($this->owner)
            ->get(route('admin.reports.export.pdf', ['tab' => 'waste']));

        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('content-type'));

        $excelResponse = $this->actingAs($this->owner)
            ->get(route('admin.reports.export.excel', ['tab' => 'waste']));

        $excelResponse->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $excelResponse->headers->get('content-type'));
    }
}

