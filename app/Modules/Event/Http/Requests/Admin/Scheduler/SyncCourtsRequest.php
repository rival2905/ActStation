<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin\Scheduler;

use Illuminate\Foundation\Http\FormRequest;

class SyncCourtsRequest extends FormRequest
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
            'venue_court_ids' => ['present', 'array', 'max:500'],
            'venue_court_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
