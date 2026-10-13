<?php

declare(strict_types=1);

namespace App\Contracts;

interface Payable
{
    public function getPayableType(): string;

    public function getPayableId(): int;

    public function getAmount(): string;

    public function getDescription(): string;
}
