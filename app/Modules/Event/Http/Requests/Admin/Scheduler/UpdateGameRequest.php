<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin\Scheduler;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameRequest extends FormRequest
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
            'venue_court_id' => ['nullable', 'integer', Rule::exists('event_courts', 'venue_court_id')->where('event_id', $this->route('event')?->id)],
            'game_time' => ['nullable', 'date'],
            'team_a_id' => ['nullable', 'integer'],
            'team_b_id' => ['nullable', 'integer'],
            'team_a_label' => ['nullable', 'string', 'max:100'],
            'team_b_label' => ['nullable', 'string', 'max:100'],
            'score_a' => ['nullable', 'integer', 'min:0'],
            'score_b' => ['nullable', 'integer', 'min:0'],
            'game_status' => ['nullable', 'string', 'in:scheduled,live,finished,cancelled,bye'],
            'match_label' => ['nullable', 'string', 'max:50'],
            'round_label' => ['nullable', 'string', 'max:50'],
        ];
    }
}
