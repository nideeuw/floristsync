<?php

namespace App\Exceptions;

use DomainException;

/** Midtrans rejected a refund (or the void that replaces it). Callers keep the order exactly as it was. */
class RefundFailedException extends DomainException {}
