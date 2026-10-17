<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Run explicitly: php artisan db:seed --class=DemoDataSeeder
 * Requires roles/users from RolesMenusSeeder. Safe to run twice.
 */
class DemoDataSeeder extends Seeder
{
    private const PLACEHOLDER_COLORS = [
        '#f9a8d4', '#fda4af', '#c4b5fd', '#93c5fd',
        '#86efac', '#fcd34d', '#fdba74', '#f0abfc',
    ];

    /** @var array<int, array{0: string, 1: string, 2: string, 3: int}> name, category, description, price */
    private const PRODUCTS = [
        ['Blush Rose Hand Bouquet', 'Hand Bouquet', 'Soft pink roses wrapped in kraft paper with eucalyptus accents.', 185000],
        ['Sunny Gerbera Hand Bouquet', 'Hand Bouquet', 'Bright gerbera daisies for a cheerful everyday gift.', 145000],
        ['Ivory Lily Hand Bouquet', 'Hand Bouquet', 'Elegant white lilies with baby\'s breath, perfect for congratulations.', 210000],
        ['Pastel Carnation Hand Bouquet', 'Hand Bouquet', 'Gentle pastel carnations tied with satin ribbon.', 165000],

        ['Velvet Rose Flower Box', 'Flower Box', 'Deep red roses arranged in a luxury velvet hat box.', 325000],
        ['Peony Dream Flower Box', 'Flower Box', 'Lush peonies in a pastel round box with soft fillers.', 385000],
        ['Tulip Meadow Flower Box', 'Flower Box', 'Fresh tulips arranged in a rustic wooden flower box.', 295000],
        ['Orchid Luxe Flower Box', 'Flower Box', 'Premium orchids in a black matte box with gold trim.', 450000],

        ['Classic Standing Flower', 'Standing Flower', 'Traditional standing arrangement for formal occasions.', 550000],
        ['Golden Condolence Standing Flower', 'Standing Flower', 'Respectful white and yellow standing piece for condolences.', 675000],
        ['Rustic Sunflower Standing Flower', 'Standing Flower', 'Warm sunflower standing display for celebrations.', 425000],
        ['Grand Hydrangea Standing Flower', 'Standing Flower', 'Show-stopping hydrangea arrangement on a tall stand.', 725000],

        ['Single Red Rose', 'Single Stems', 'One long-stem red rose, simply wrapped.', 25000],
        ['Single White Tulip', 'Single Stems', 'A single elegant white tulip stem.', 35000],
        ['Single Sunflower Stem', 'Single Stems', 'One cheerful sunflower stem with greenery.', 30000],
        ['Single Pink Carnation', 'Single Stems', 'A delicate pink carnation, perfect as a small token.', 28000],

        ['Sweet Bloom Gift Hamper', 'Gift Hampers', 'Bouquet paired with chocolates and a greeting card.', 350000],
        ['Golden Appreciation Hamper', 'Gift Hampers', 'Flowers, cookies, and tea set in a woven basket.', 475000],
        ['Dreamy Treats Hamper', 'Gift Hampers', 'Mini bouquet with assorted sweets for any celebration.', 285000],
        ['Rustic Garden Hamper', 'Gift Hampers', 'Garden-style arrangement with honey and dried snacks.', 395000],
    ];

    private const MEMBERS = [
        ['Demo Member', 'demo.member1@floristsync.test', '081211110001', 'Jl. Melati No. 1, Jakarta', '3511000'],
        ['Sari Wijaya', 'demo.member2@floristsync.test', '081211110002', 'Jl. Anggrek No. 12, Bandung', '4011100'],
        ['Budi Santoso', 'demo.member3@floristsync.test', '081211110003', 'Jl. Kenanga No. 5, Surabaya', '6011100'],
        ['Citra Lestari', 'demo.member4@floristsync.test', '081211110004', 'Jl. Dahlia No. 8, Yogyakarta', '5511100'],
    ];

    /** @var array<int, array{status: string, is_delivery: bool, days_ago: int, minutes_ago: int, lines: array<int, array{0: int, 1: int}>, payment_method: string}> */
    private const ORDERS = [
        [
            'status' => Transaction::PENDING_PAYMENT,
            'is_delivery' => false,
            'days_ago' => 0,
            'minutes_ago' => 10,
            'lines' => [[0, 1], [12, 2]],
            'payment_method' => 'qris',
        ],
        [
            'status' => Transaction::PROCESSING,
            'is_delivery' => true,
            'days_ago' => 3,
            'minutes_ago' => 0,
            'lines' => [[5, 1], [16, 1]],
            'payment_method' => 'gopay',
        ],
        [
            'status' => Transaction::READY_FOR_PICKUP,
            'is_delivery' => false,
            'days_ago' => 2,
            'minutes_ago' => 0,
            'lines' => [[8, 1], [10, 1]],
            'payment_method' => 'credit_card',
        ],
        [
            'status' => Transaction::SHIPPED,
            'is_delivery' => true,
            'days_ago' => 4,
            'minutes_ago' => 0,
            'lines' => [[4, 1], [13, 1]],
            'payment_method' => 'qris',
        ],
        [
            'status' => Transaction::COMPLETED,
            'is_delivery' => false,
            'days_ago' => 7,
            'minutes_ago' => 0,
            'lines' => [[1, 1], [2, 1], [14, 2]],
            'payment_method' => 'gopay',
        ],
        [
            'status' => Transaction::CANCELLED,
            'is_delivery' => false,
            'days_ago' => 5,
            'minutes_ago' => 0,
            'lines' => [[7, 1]],
            'payment_method' => 'qris',
        ],
    ];

    public function run(): void
    {
        $memberRole = Role::where('role_name', Role::MEMBER)->first();

        if (! $memberRole) {
            throw new RuntimeException('Roles not found. Run: php artisan db:seed --class=RolesMenusSeeder');
        }

        $inventory = app(InventoryService::class);

        $categories = $this->seedCategories();
        $products = $this->seedProducts($categories, $inventory);
        $members = $this->seedMembers($memberRole);
        $this->seedOnlineOrders($products, $members);
    }

    /** @return array<string, Category> */
    private function seedCategories(): array
    {
        $data = [
            'Hand Bouquet' => 'Hand-tied bouquets for birthdays, anniversaries, and everyday gifts.',
            'Flower Box' => 'Premium arrangements in elegant hat and gift boxes.',
            'Standing Flower' => 'Tall displays for grand openings, condolences, and events.',
            'Single Stems' => 'Single flower stems for simple, thoughtful gestures.',
            'Gift Hampers' => 'Bundled flowers with treats and keepsakes.',
        ];

        $categories = [];

        foreach ($data as $name => $description) {
            $categories[$name] = Category::firstOrCreate(
                ['category_name' => $name],
                ['description' => $description]
            );
        }

        return $categories;
    }

    /**
     * @param array<string, Category> $categories
     * @return array<int, Product> products in self::PRODUCTS order
     */
    private function seedProducts(array $categories, InventoryService $inventory): array
    {
        Storage::disk('public')->makeDirectory('products');

        $products = [];

        foreach (self::PRODUCTS as $i => [$name, $categoryName, $description, $price]) {
            $image = 'products/demo-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).'.svg';

            $product = Product::firstOrCreate(
                ['product_name' => $name],
                [
                    'category_id' => $categories[$categoryName]->id,
                    'description' => $description,
                    'price' => $price,
                    'image' => $image,
                    'stock' => 0,
                    'status' => Product::STATUS_AVAILABLE,
                ]
            );

            $this->writePlaceholderImage($image, $name, $i);

            if ($product->stock === 0 && ! $product->stocks()->exists()) {
                $inventory->add($product, 12 + (($i * 3) % 19), 'in', 'Demo seed');
            }

            $products[] = $product->fresh();
        }

        return $products;
    }

    /** @return array<int, User> */
    private function seedMembers(Role $memberRole): array
    {
        $members = [];

        foreach (self::MEMBERS as [$name, $email, $phone, $address, $postalCode]) {
            $members[] = User::firstOrCreate(
                ['email' => $email],
                [
                    'role_id' => $memberRole->id,
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'phone' => $phone,
                    'address' => $address,
                    'postal_code' => $postalCode,
                ]
            );
        }

        return $members;
    }

    /**
     * @param array<int, Product> $products
     * @param array<int, User> $members
     */
    private function seedOnlineOrders(array $products, array $members): void
    {
        $cancelledBy = User::whereHas('role', fn ($q) => $q->where('role_name', Role::OWNER))->first();

        foreach (self::ORDERS as $i => $def) {
            $midtransOrderId = 'FS-DEMO-'.strtoupper($def['status']).'-'.($i + 1);

            if (Payment::where('midtrans_order_id', $midtransOrderId)->exists()) {
                continue;
            }

            $date = $def['minutes_ago'] > 0
                ? now()->subMinutes($def['minutes_ago'])
                : now()->subDays($def['days_ago'])->setTime(10 + $i, 15);

            $member = $members[$i % count($members)];

            $lines = array_map(function ($line) use ($products) {
                $product = $products[$line[0]];
                $qty = $line[1];

                return [
                    'product' => $product,
                    'qty' => $qty,
                    'unit_price' => $product->price,
                    'subtotal' => $product->price * $qty,
                ];
            }, $def['lines']);

            $shipping = $def['is_delivery'] ? 25000 : 0;
            $total = array_sum(array_column($lines, 'subtotal')) + $shipping;

            $paid = ! in_array($def['status'], [Transaction::PENDING_PAYMENT, Transaction::CANCELLED], true);

            $transaction = Transaction::create([
                'user_id' => $member->id,
                'transaction_type' => Transaction::TYPE_ONLINE,
                'status' => $def['status'],
                'shipping_address' => $def['is_delivery']
                    ? "{$member->name} ({$member->phone})\n{$member->address}"
                    : null,
                'courier' => $def['is_delivery'] ? 'jne' : null,
                'tracking_number' => $def['status'] === Transaction::SHIPPED
                    ? 'FSDEMO'.str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT)
                    : null,
                'total' => $total,
                'notes' => 'Demo data',
                'cancelled_at' => $def['status'] === Transaction::CANCELLED ? $date : null,
                'cancel_reason' => $def['status'] === Transaction::CANCELLED
                    ? 'Customer changed their mind (demo).'
                    : null,
                'cancelled_by' => $def['status'] === Transaction::CANCELLED ? $cancelledBy?->id : null,
                'created_on' => $date,
                'modified_on' => $date,
            ]);

            foreach ($lines as $line) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $line['product']->id,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                ]);

                if ($def['status'] !== Transaction::CANCELLED) {
                    Product::whereKey($line['product']->id)->decrement('stock', $line['qty']);
                }
            }

            Payment::create([
                'transaction_id' => $transaction->id,
                'midtrans_order_id' => $midtransOrderId,
                'payment_method' => $def['payment_method'],
                'payment_status' => match (true) {
                    $paid => 'settlement',
                    $def['status'] === Transaction::CANCELLED => 'cancel',
                    default => 'pending',
                },
                'amount' => $total,
                'payment_date' => $paid ? $date : null,
                'created_on' => $date,
                'modified_on' => $date,
            ]);
        }
    }

    private function writePlaceholderImage(string $path, string $productName, int $index): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return;
        }

        $color = self::PLACEHOLDER_COLORS[$index % count(self::PLACEHOLDER_COLORS)];
        $initials = strtoupper(implode('', array_map(
            fn ($word) => mb_substr($word, 0, 1),
            array_slice(explode(' ', $productName), 0, 2)
        )));

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">
  <rect width="600" height="600" fill="{$color}" opacity="0.25"/>
  <circle cx="300" cy="280" r="150" fill="{$color}" opacity="0.85"/>
  <circle cx="220" cy="220" r="70" fill="{$color}"/>
  <circle cx="380" cy="220" r="70" fill="{$color}"/>
  <circle cx="300" cy="160" r="70" fill="{$color}"/>
  <circle cx="300" cy="260" r="40" fill="#fff" opacity="0.9"/>
  <text x="300" y="520" font-family="Segoe UI, Arial, sans-serif" font-size="64" font-weight="700" fill="#334155" text-anchor="middle">{$initials}</text>
</svg>
SVG;

        $disk->put($path, $svg);
    }
}
