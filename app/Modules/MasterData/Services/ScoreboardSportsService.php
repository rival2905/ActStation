<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Services;

use App\Contracts\ScoreboardSports;
use App\Modules\MasterData\Models\SportCategory;

class ScoreboardSportsService implements ScoreboardSports
{
    public function all(): array
    {
        return SportCategory::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug'])
            ->map(fn ($sport) => ['id' => (int) $sport->id, 'name' => $sport->name, 'slug' => $sport->slug, 'supported' => $sport->slug === 'basketball'])->all();
    }

    public function supports(int $sportCategoryId): bool
    {
        return SportCategory::whereKey($sportCategoryId)->where('is_active', true)->where('slug', 'basketball')->exists();
    }
}
