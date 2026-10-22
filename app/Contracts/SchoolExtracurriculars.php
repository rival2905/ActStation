<?php

declare(strict_types=1);

namespace App\Contracts;

interface SchoolExtracurriculars
{
    /**
     * @param  list<int>  $userIds
     */
    public function sync(int $communityId, int $sportCategoryId, array $userIds): ?int;
}
