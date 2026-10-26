<?php

declare(strict_types=1);

namespace App\Contracts;

interface AssistantEventSearch
{
    public function search(string $query = '', ?string $from = null, ?string $to = null): array;
}
