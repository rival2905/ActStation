<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Panel;

use App\Modules\Event\Services\EventCommitteeAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameCourtRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->routeIs('admin.*') || app(EventCommitteeAccess::class)->allows($this->route('event'), $this->user(), 'schedule.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'venue_court_id' => ['present', 'nullable', 'integer', Rule::exists('event_courts', 'venue_court_id')->where('event_id', $this->route('event')->id)],
        ];
    }
}
