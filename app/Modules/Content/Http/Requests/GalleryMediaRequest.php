<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class GalleryMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:photo,video'],
            'file' => ['required_if:type,photo', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'video_url' => [
                'required_if:type,video',
                'nullable',
                'url',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }
                    $host = strtolower((string) parse_url($value, PHP_URL_HOST));
                    $allowedHosts = [
                        'youtube.com',
                        'www.youtube.com',
                        'm.youtube.com',
                        'youtu.be',
                        'vimeo.com',
                        'www.vimeo.com',
                        'player.vimeo.com',
                    ];
                    if (! in_array($host, $allowedHosts, true)) {
                        $fail('URL video harus berasal dari domain YouTube atau Vimeo yang diizinkan.');
                    }
                },
            ],
            'caption' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
