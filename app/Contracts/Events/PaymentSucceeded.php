<?php

declare(strict_types=1);

namespace App\Contracts\Events;

final readonly class PaymentSucceeded
{
    public function __construct(
        public int $transactionId,
        public string $payableType,
        public int $payableId,
        public ?string $amount = null,
    ) {}
}
