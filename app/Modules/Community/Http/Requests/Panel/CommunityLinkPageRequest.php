<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Panel;

use App\Modules\Community\Services\CommunityLinkPageService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunityLinkPageRequest extends FormRequest
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
        $rules = ['panel_community_id' => ['sometimes', 'integer', 'min:1']];
        if ($this->routeIs('panel.community-links.store')) {
            return $rules + [
                'handle' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'not_regex:/^\d+$/', Rule::notIn(['admin', 'api', 'panel', 'login', 'register', 'support', 'help', 'actstation', 'assets', 'storage']), Rule::unique('community_link_pages', 'handle')],
                'title' => ['required', 'string', 'max:100'],
                'theme_id' => ['required', 'string', 'max:40'],
            ];
        }
        $rules['revision'] = ['required', 'integer', 'min:1'];
        if (! $this->routeIs('panel.community-links.update')) {
            return $rules;
        }
        $url = function ($attribute, $value, $fail): void {
            if (! is_string($value) || ! CommunityLinkPageService::safeUrl($value)) {
                $fail('Masukkan URL HTTP atau HTTPS yang valid, tanpa username/password di URL.');
            }
        };

        return $rules + [
            'conflict_reviewed' => [(int) session('link_page_conflict', 0) === (int) $this->route('page')?->id ? 'accepted' : 'sometimes'],
            'title' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:300'],
            'theme_id' => ['required', 'string', 'max:40'],
            'categories' => ['sometimes', 'array', 'max:10'],
            'categories.*' => ['array:id,name,visible'],
            'categories.*.id' => ['required', 'uuid', 'distinct'],
            'categories.*.name' => ['required', 'string', 'max:40'],
            'categories.*.visible' => ['sometimes', 'boolean'],
            'links' => ['sometimes', 'array', 'max:30'],
            'links.*' => ['array:id,label,url,subtitle,category_id,visible'],
            'links.*.id' => ['required', 'uuid', 'distinct'],
            'links.*.label' => ['required', 'string', 'max:80'],
            'links.*.url' => ['required', 'string', 'max:2048', $url],
            'links.*.subtitle' => ['nullable', 'string', 'max:140'],
            'links.*.category_id' => ['nullable', 'uuid'],
            'links.*.visible' => ['required', 'boolean'],
            'socials' => ['sometimes', 'array', 'max:8'],
            'socials.*' => ['array:label,url'],
            'socials.*.label' => ['required', 'string', 'max:40'],
            'socials.*.url' => ['required', 'string', 'max:2048', $url],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->routeIs('panel.community-links.update')) {
                return;
            }
            $categories = collect($this->input('categories', []));
            $names = $categories->pluck('name')->map(fn ($name) => mb_strtolower(trim($name)));
            if ($names->unique()->count() !== $names->count() || $names->intersect(['semua', 'umum'])->isNotEmpty()) {
                $validator->errors()->add('categories', 'Nama kategori harus unik; Semua dan Umum merupakan filter sistem.');
            }
            foreach ($this->input('links', []) as $index => $link) {
                if (! empty($link['category_id']) && ! $categories->contains('id', $link['category_id'])) {
                    $validator->errors()->add('links.'.$index.'.category_id', 'Kategori tidak tersedia pada halaman ini.');
                }
            }
        }];
    }
}
