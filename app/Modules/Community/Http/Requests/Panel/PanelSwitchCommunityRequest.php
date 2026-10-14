<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Panel;

use App\Modules\Community\Models\CommunityOfficial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PanelSwitchCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('superadmin') || $user->hasRole('admin')) {
            return true;
        }

        $communityId = (int) $this->input('community_id');

        return CommunityOfficial::query()
            ->where('user_id', $user->id)
            ->where('community_id', $communityId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->whereHas('community', fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'community_id' => [
                'required',
                'integer',
                Rule::exists('communities', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
        ];
    }
}
