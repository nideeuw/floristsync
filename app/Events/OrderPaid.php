<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** An online order was paid. It is only dispatched after the database transaction that marked it paid has committed. */
class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $transactionId) {}
}
