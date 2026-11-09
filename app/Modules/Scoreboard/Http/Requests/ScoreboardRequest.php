<?php

declare(strict_types=1);

namespace App\Modules\Scoreboard\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScoreboardRequest extends FormRequest
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
        $rules = ['panel_community_id' => ['required', 'integer', 'min:1']];
        if ($this->routeIs('scoreboard.sessions.store')) {
            return $rules + [
                'sport_category_id' => ['required', 'integer', 'min:1'],
                'mode' => ['required', 'in:quick,recorded'],
                'kind' => ['required', 'in:practice,friendly'],
                'home_name' => [$this->input('mode') === 'recorded' ? 'required' : 'nullable', 'string', 'max:40'],
                'away_name' => [$this->input('mode') === 'recorded' ? 'required' : 'nullable', 'string', 'max:40'],
                'location' => ['nullable', 'string', 'max:150'],
                'schema_version' => ['prohibited'], 'state' => ['prohibited'], 'community_id' => ['prohibited'],
            ];
        }
        $rules += ['command_id' => ['required', 'uuid'], 'expected_revision' => ['required', 'integer', 'min:0']];
        if ($this->routeIs('scoreboard.sessions.finish')) {
            return $rules + [
                'record_result' => ['required', 'boolean'],
                'home_name' => ['required_if:record_result,true,1', 'nullable', 'string', 'max:40'],
                'away_name' => ['required_if:record_result,true,1', 'nullable', 'string', 'max:40'],
                'kind' => ['required_if:record_result,true,1', 'nullable', 'in:practice,friendly'],
                'location' => ['nullable', 'string', 'max:150'],
                'score' => ['prohibited'], 'result' => ['prohibited'],
            ];
        }

        return $rules + [
            'session_id' => ['required', 'uuid'], 'schema_version' => ['required', 'in:basketball.v1'],
            'authority_epoch' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'max:60'], 'payload' => ['present', 'array'],
            'expires_at' => ['required', 'date'], 'actor_id' => ['prohibited'], 'authority' => ['prohibited'],
        ];
    }
}
