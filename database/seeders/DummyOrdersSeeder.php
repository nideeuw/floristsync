<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DummyOrdersSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        $cashier = User::whereHas('role', fn($q) => $q->where('role_name', Role::CASHIER))->first();
        $memberRoleId = Role::where('role_name', Role::MEMBER)->value('id');
        $members = User::factory()->count(5)->create(['role_id' => $memberRoleId]);

        for ($i = 0; $i < 60; $i++) {
            $isPos = fake()->boolean(55);
            $isDelivery = ! $isPos && fake()->boolean(60);

            $date = now()
                ->subDays(fake()->numberBetween(0, 13))
                ->setTime(fake()->numberBetween(8, 19), fake()->numberBetween(0, 59));

            $status = $isPos
                ? Transaction::COMPLETED
                : fake()->randomElement($isDelivery
                    ? [
                        Transaction::PENDING_PAYMENT,
                        Transaction::PROCESSING,
                        Transaction::PROCESSING,
                        Transaction::SHIPPED,
                        Transaction::COMPLETED,
                        Transaction::COMPLETED,
                        Transaction::CANCELLED,
                    ]
                    : [
                        Transaction::PENDING_PAYMENT,
                        Transaction::PROCESSING,
                        Transaction::READY_FOR_PICKUP,
                        Transaction::COMPLETED,
                        Transaction::COMPLETED,
                        Transaction::CANCELLED,
                    ]);

            $lines = $products->random(fake()->numberBetween(1, 3))->map(function ($product) {
                $qty = fake()->numberBetween(1, 3);

                return ['product' => $product, 'qty' => $qty, 'subtotal' => $product->price * $qty];
            });

            $shipping = $isDelivery ? 25000 : 0;

            $transaction = Transaction::create([
                'user_id' => $isPos ? null : $members->random()->id,
                'cashier_id' => $isPos ? $cashier?->id : null,
                'transaction_type' => $isPos ? Transaction::TYPE_POS : Transaction::TYPE_ONLINE,
                'status' => $status,
                'shipping_address' => $isDelivery
                    ? fake()->name() . ' (' . fake()->phoneNumber() . ")\n" . fake()->address()
                    : null,
                'courier' => $isDelivery ? fake()->randomElement(['jne', 'tiki', 'sicepat', 'jnt']) : null,
                'tracking_number' => ($isDelivery && in_array($status, [Transaction::SHIPPED, Transaction::COMPLETED], true))
                    ? strtoupper(Str::random(12))
                    : null,
                'total' => $lines->sum('subtotal') + $shipping,
                'created_on' => $date,
                'modified_on' => $date,
            ]);

            foreach ($lines as $line) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $line['product']->id,
                    'qty' => $line['qty'],
                    'unit_price' => $line['product']->price,
                    'subtotal' => $line['subtotal'],
                ]);
            }

            $paid = ! in_array($status, [Transaction::PENDING_PAYMENT, Transaction::CANCELLED], true);

            Payment::create([
                'transaction_id' => $transaction->id,
                'midtrans_order_id' => $isPos ? null : 'FS-' . $transaction->id . '-' . strtoupper(Str::random(6)),
                'payment_method' => $isPos
                    ? fake()->randomElement(['cash', 'qris', 'debit'])
                    : fake()->randomElement(['qris', 'bank_transfer', 'gopay']),
                'payment_status' => match (true) {
                    $paid => 'settlement',
                    $status === Transaction::CANCELLED => 'expire',
                    default => 'pending',
                },
                'amount' => $transaction->total,
                'payment_date' => $paid ? $date : null,
                'created_on' => $date,
                'modified_on' => $date,
            ]);
        }
    }
}
