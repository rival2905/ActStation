<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $community = $this->route('community');
        $id = is_object($community) ? $community->id : $community;

        $merge = [];
        if (! $this->filled('slug') && $this->filled('name')) {
            $merge['slug'] = Str::slug((string) $this->input('name'));
        }
        if ($this->has('is_active')) {
            $merge['is_active'] = $this->boolean('is_active');
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $community = $this->route('community');
        $id = is_object($community) ? $community->id : $community;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('communities', 'slug')->ignore($id)],
            'sport_category_id' => ['nullable', 'integer', 'exists:sport_categories,id'],
            'subtype' => ['required', 'in:school,university,institution,club,free_association'],
            'npsn' => ['nullable', 'string', 'max:20', Rule::unique('communities', 'npsn')->ignore($id)],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'address' => ['nullable', 'string'],
            'province_id' => ['nullable', 'string', 'max:10'],
            'city_id' => ['nullable', 'string', 'max:10'],
            'subdistrict_id' => ['nullable', 'string', 'max:15'],
            'village_id' => ['nullable', 'string', 'max:15'],
            'postal_code' => ['nullable', 'string', 'max:5'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
