<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Resources;

use App\Contracts\DTOs\OfficialDto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int|null $id
 * @property int|null $user_id
 * @property string|null $duty
 * @property User|null $user
 */
class OfficialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof OfficialDto) {
            return [
                'userId' => $this->resource->userId,
                'user_id' => $this->resource->userId,
                'name' => $this->resource->name,
                'duty' => null,
            ];
        }

        $userId = (int) ($this->user_id ?? $this->userId ?? $this->user?->id ?? $this->id);
        $name = (string) ($this->name ?? $this->user?->name ?? '');

        return [
            'id' => $this->id ?? null,
            'userId' => $userId,
            'user_id' => $userId,
            'name' => $name,
            'duty' => $this->duty ?? null,
        ];
    }
}
