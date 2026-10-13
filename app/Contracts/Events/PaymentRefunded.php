<?php

declare(strict_types=1);

namespace App\Contracts\Events;

final readonly class PaymentRefunded
{
    public function __construct(
        public int $transactionId,
        public int $refundId,
        public string $payableType,
        public int $payableId,
        public ?string $amount = null,
    ) {}
}
