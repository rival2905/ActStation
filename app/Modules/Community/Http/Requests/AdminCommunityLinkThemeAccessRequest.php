<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdminCommunityLinkThemeAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active && $this->user()->can('community-links.moderate');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->isMethod('GET')
            ? ['q' => ['nullable', 'string', 'max:100']]
            : ['enabled' => ['required', 'boolean']];
    }
}
