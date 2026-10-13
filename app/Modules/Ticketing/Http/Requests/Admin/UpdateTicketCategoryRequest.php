<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Requests\Admin;

use App\Modules\Ticketing\Models\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('ticketCategory');
        $minQuota = 1;
        if ($category instanceof TicketCategory) {
            $minQuota = max(1, (int) $category->sold_count);
        } elseif (is_numeric($category)) {
            $minQuota = max(1, (int) TicketCategory::where('id', $category)->value('sold_count'));
        }

        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'quota' => ['required', 'integer', "min:{$minQuota}"],
            'description' => ['nullable', 'string'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
