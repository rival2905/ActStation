<?php

declare(strict_types=1);

namespace App\Contracts;

interface ScoreboardSports
{
    /** @return list<array{id:int,name:string,slug:string,supported:bool}> */
    public function all(): array;

    public function supports(int $sportCategoryId): bool;
}
