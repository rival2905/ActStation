<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin\Scheduler;

use Illuminate\Foundation\Http\FormRequest;

class PlotOfficialsRequest extends FormRequest
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
            'officials' => ['required', 'array'],
            'officials.*.user_id' => ['required', 'integer'],
            'officials.*.duty' => ['required', 'string', 'max:50'],
        ];
    }
}
