<?php

declare(strict_types=1);

namespace App\Modules\Scoreboard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShotAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('scoreboard.shot.*') || (bool) $this->user()?->is_active;
    }

    public function rules(): array
    {
        if ($this->routeIs('scoreboard.shot.command')) {
            return [
                'command_id' => ['required', 'uuid'], 'session_id' => ['required', 'uuid'],
                'schema_version' => ['required', 'in:basketball.v1'], 'authority_epoch' => ['required', 'integer', 'min:1'],
                'expected_revision' => ['required', 'integer', 'min:0'], 'expires_at' => ['required', 'date'],
                'type' => ['required', 'string', 'max:60'], 'payload' => ['present', 'array'],
                'actor_id' => ['prohibited'], 'authority' => ['prohibited'],
            ];
        }

        return ['panel_community_id' => ['required', 'integer', 'min:1']]
            + ($this->isMethod('POST') ? ['hours' => ['required', 'integer', 'in:1,2,4']] : []);
    }
}
