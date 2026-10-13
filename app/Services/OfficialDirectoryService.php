<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DTOs\OfficialDto;
use App\Contracts\OfficialDirectory;
use App\Models\User;
use Illuminate\Support\Collection;

class OfficialDirectoryService implements OfficialDirectory
{
    /**
     * @return Collection<int, OfficialDto>
     */
    public function search(string $duty): Collection
    {
        // ponytail: query checks roles matching duty or referee/official baseline; upgrade to domain-scoped officials table if duty types expand.
        return User::query()
            ->where(function ($query) use ($duty): void {
                $query->whereHas('roles', function ($q) use ($duty): void {
                    $q->whereIn('name', ['referee', 'official', $duty]);
                })->orWhereHas('permissions', function ($q): void {
                    $q->where('name', 'tournament.official');
                });
            })
            ->get()
            ->map(fn (User $user): OfficialDto => new OfficialDto(
                userId: (int) $user->id,
                name: (string) $user->name,
            ));
    }
}
