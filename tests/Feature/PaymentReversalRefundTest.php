<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Transaction;
use App\Services\MidtransService;
use App\Services\PaymentReversalService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentReversalRefundTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER_ID = 'FS-TEST-ABC123';

    private function paidCardOrder(string $paymentStatus = 'capture'): Transaction
    {
        $transaction = Transaction::create([
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => Transaction::PROCESSING,
            'total' => 150000,
        ]);

        Payment::create([
            'transaction_id' => $transaction->id,
            'midtrans_order_id' => self::ORDER_ID,
            'payment_method' => 'credit_card',
            'payment_status' => $paymentStatus,
            'amount' => 150000,
            'payment_date' => now(),
        ]);

        return $transaction->refresh();
    }

    private function remote(string $status): array
    {
        return [
            'transaction_status' => $status,
            'fraud_status' => 'accept',
            'payment_type' => 'credit_card',
            'gross_amount' => '150000.00',
        ];
    }

    private function paymentStatus(Transaction $transaction): string
    {
        return (string) Payment::where('transaction_id', $transaction->id)->value('payment_status');
    }

    public function test_captured_card_payment_is_voided(): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->once()->andReturn($this->remote('capture'));
            $mock->shouldReceive('cancelTransaction')->once()->with(self::ORDER_ID);
            $mock->shouldNotReceive('refundTransaction');
        });

        $transaction = $this->paidCardOrder();

        app(PaymentReversalService::class)->refund($transaction);

        $this->assertSame('cancel', $this->paymentStatus($transaction));
    }

    public function test_refund_is_used_when_the_void_is_refused_because_the_payment_settled(): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->twice()->andReturn(
                $this->remote('capture'),
                $this->remote('settlement'),
            );
            $mock->shouldReceive('cancelTransaction')->once()
                ->andThrow(new DomainException('Transaction status cannot be updated.'));
            $mock->shouldReceive('refundTransaction')->once()
                ->andReturn(['status_code' => '200', 'transaction_status' => 'refund']);
        });

        $transaction = $this->paidCardOrder();

        app(PaymentReversalService::class)->refund($transaction);

        $this->assertSame('refund', $this->paymentStatus($transaction));
    }

    public function test_void_error_is_shown_when_the_payment_did_not_settle(): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->twice()->andReturn(
                $this->remote('capture'),
                $this->remote('capture'),
            );
            $mock->shouldReceive('cancelTransaction')->once()
                ->andThrow(new DomainException('Midtrans is not reachable right now. Please try again.'));
            $mock->shouldNotReceive('refundTransaction');
        });

        $transaction = $this->paidCardOrder();

        try {
            app(PaymentReversalService::class)->refund($transaction);
            $this->fail('A DomainException was expected.');
        } catch (DomainException $e) {
            $this->assertSame('Midtrans is not reachable right now. Please try again.', $e->getMessage());
        }

        $this->assertSame('capture', $this->paymentStatus($transaction));
    }

    public function test_settled_card_payment_is_refunded_without_a_void(): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->once()->andReturn($this->remote('settlement'));
            $mock->shouldNotReceive('cancelTransaction');
            $mock->shouldReceive('refundTransaction')->once()
                ->andReturn(['status_code' => '200', 'transaction_status' => 'refund']);
        });

        $transaction = $this->paidCardOrder('settlement');

        app(PaymentReversalService::class)->refund($transaction);

        $this->assertSame('refund', $this->paymentStatus($transaction));
    }
}