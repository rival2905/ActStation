<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\DTOs\OfficialDto;
use Illuminate\Support\Collection;

interface OfficialDirectory
{
    /**
     * @return Collection<int, OfficialDto>
     */
    public function search(string $duty): Collection;
}
