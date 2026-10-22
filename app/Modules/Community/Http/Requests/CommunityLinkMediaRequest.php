<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CommunityLinkMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1'],
            'asset_kind' => ['sometimes', 'in:avatar,background'],
            'panel_community_id' => ['sometimes', 'integer', 'min:1'],
            'avatar' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'],
        ];
    }
}
