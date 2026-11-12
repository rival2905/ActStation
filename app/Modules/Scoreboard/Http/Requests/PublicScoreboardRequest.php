<?php

declare(strict_types=1);

namespace App\Modules\Scoreboard\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PublicScoreboardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ! $this->user() || (bool) $this->user()->is_active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $prohibited = ['state' => ['prohibited'], 'community_id' => ['prohibited'], 'actor_id' => ['prohibited'], 'authority' => ['prohibited']];
        if ($this->routeIs('scoreboard.personal.store')) {
            return $prohibited + ['sport_category_id' => ['required', 'integer', 'min:1'],
                'home_name' => ['nullable', 'string', 'max:40'], 'away_name' => ['nullable', 'string', 'max:40']];
        }
        if ($this->routeIs('scoreboard.personal.claim')) {
            return $prohibited;
        }
        $rules = $prohibited + ['command_id' => ['required', 'uuid'], 'expected_revision' => ['required', 'integer', 'min:0']];
        if ($this->routeIs('scoreboard.personal.finish')) {
            return $rules + ['record_result' => ['required', 'boolean', 'declined']];
        }

        return $rules + ['session_id' => ['required', 'uuid'], 'schema_version' => ['required', 'in:basketball.v1'],
            'authority_epoch' => ['required', 'integer', 'min:1'], 'type' => ['required', 'string', 'max:60'],
            'payload' => ['present', 'array'], 'expires_at' => ['required', 'date']];
    }
}
