<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Panel;

use App\Contracts\EventLocationLookup;
use App\Modules\Event\Services\EventCommitteeAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTicketCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->routeIs('admin.*') || app(EventCommitteeAccess::class)->allows($this->route('event'), $this->user(), 'ticketing.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'quota' => ['required', 'integer', 'min:'.max(1, (int) $this->route('category')?->sold_count)],
            'access_type' => ['required', 'in:fullday,per_game,tournament_pass'],
            'game_id' => ['exclude_unless:access_type,per_game', 'required', 'integer', Rule::exists('games', 'id')->where('event_id', $this->route('event')->id)],
            'seating_tier' => ['required', 'in:standard,cushioned,courtside,vip_box'],
            'seating_zone' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'location_scope' => ['sometimes', 'required', 'in:all,venue,court'],
            'venue_id' => ['exclude_unless:location_scope,venue', 'required', 'integer', Rule::in(array_column(app(EventLocationLookup::class)->options($this->route('event')->id)['venues'], 'id'))],
            'venue_court_id' => ['exclude_unless:location_scope,court', 'required', 'integer', Rule::exists('event_courts', 'venue_court_id')->where('event_id', $this->route('event')->id)],
        ];
    }
}
