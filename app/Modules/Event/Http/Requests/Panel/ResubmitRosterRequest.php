<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Panel;

use App\Contracts\CommunityLookup;
use App\Modules\Event\Models\EventParticipant;
use Illuminate\Foundation\Http\FormRequest;

class ResubmitRosterRequest extends FormRequest
{
    public function authorize(CommunityLookup $communityLookup): bool
    {
        $participant = $this->route('participant');
        if (! $participant instanceof EventParticipant) {
            $participant = EventParticipant::query()->find($participant);
        }

        if (! $participant || $participant->status !== 'revision_needed') {
            return false;
        }

        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        if (! $participant->community_id) {
            return false;
        }

        $userCommunity = $communityLookup->findForUser((int) $user->id);

        return $userCommunity !== null && (int) $userCommunity->id === (int) $participant->community_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'panel_community_id' => ['nullable'],
            'members' => ['required', 'array'],
            'members.*.user_id' => ['required', 'integer'],
            'members.*.team_role' => ['nullable', 'string', 'max:50'],
            'members.*.jersey_number' => ['nullable', 'integer', 'min:0', 'max:99'],
            'members.*.is_captain' => ['nullable', 'boolean'],
            'members.*.position' => ['nullable', 'string', 'max:50'],
            'team_documents' => ['nullable', 'array'],
            'team_documents.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'athlete_documents' => ['nullable', 'array'],
            'athlete_documents.*.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (): void {
            if ($this->has('panel_community_id')) {
                $raw = $this->input('panel_community_id');
                if (is_array($raw)) {
                    abort(422, 'Format panel_community_id tidak valid.');
                }

                if (! is_numeric($raw) || (int) $raw <= 0) {
                    abort(422, 'ID komunitas tidak valid.');
                }

                $user = $this->user();
                $communityLookup = app(CommunityLookup::class);
                $userCommunity = $communityLookup->findForUser($user ? (int) $user->id : 0);

                if (! $userCommunity || (int) $raw !== (int) $userCommunity->id) {
                    abort(422, 'Konteks komunitas telah berubah. Silakan muat ulang halaman.');
                }
            }
        });
    }
}
