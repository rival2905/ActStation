<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\TeamPersonnelProvider;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventParticipant;
use Illuminate\Validation\ValidationException;

class TeamPersonnelRoster
{
    public function __construct(private readonly TeamPersonnelProvider $provider) {}

    public function options(Event $event, int $communityId): array
    {
        $roles = collect($this->provider->roles())->keyBy('id');

        return [
            'requirements' => collect($event->personnel_requirements ?? [])->map(fn ($rule) => array_merge($rule, ['name' => $roles->get($rule['role_id'])['name'] ?? 'Peran tidak tersedia']))->values()->all(),
            'people' => $this->provider->eligiblePeople($communityId),
        ];
    }

    public function initial(Event $event, int $communityId, int $creatorId): array
    {
        $manager = collect($this->provider->roles())->first(fn ($role) => $role['code'] === 'manager' && $role['is_active']);
        $allowed = $manager && collect($event->personnel_requirements ?? [])->contains(fn ($r) => (int) $r['role_id'] === (int) $manager['id']);
        $creator = collect($this->provider->eligiblePeople($communityId))->first(fn ($p) => (int) $p['user_id'] === $creatorId && $p['is_official']);

        return $allowed && $creator ? [['user_id' => $creatorId, 'role_id' => $manager['id']]] : [];
    }

    public function validate(Event $event, int $communityId, array $selection, bool $final = true): array
    {
        $rules = collect($event->personnel_requirements ?? [])->keyBy('role_id');
        $roles = collect($this->provider->roles())->keyBy('id');
        $people = collect($this->provider->eligiblePeople($communityId))->keyBy('user_id');
        $seen = [];
        $rows = [];
        if (count($selection) > 200) {
            throw ValidationException::withMessages(['personnel' => 'Maksimal 200 penugasan tenaga pendukung.']);
        }
        foreach ($selection as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['personnel' => 'Format personel tidak valid.']);
            }
            $uid = filter_var($row['user_id'] ?? null, FILTER_VALIDATE_INT);
            $rid = filter_var($row['role_id'] ?? null, FILTER_VALIDATE_INT);
            $key = $uid.':'.$rid;
            if (! $uid || ! $rid || ! $rules->has($rid) || ! $roles->has($rid) || ! $roles->get($rid)['is_active'] || ! $people->has($uid) || isset($seen[$key])) {
                throw ValidationException::withMessages(['personnel' => 'Personel/peran tidak diizinkan, tidak aktif, bukan anggota komunitas, atau duplikat.']);
            }
            $seen[$key] = true;
            $rows[] = ['user_id' => $uid, 'role_id' => $rid, 'role_name' => $roles->get($rid)['name']];
        }
        foreach ($rules as $rid => $rule) {
            $count = collect($rows)->where('role_id', $rid)->count();
            if ($count > (int) $rule['max'] || ($final && $count < (int) $rule['min'])) {
                throw ValidationException::withMessages(['personnel' => ($roles->get($rid)['name'] ?? 'Personel').' membutuhkan '.($final ? $rule['min'] : 0).'–'.$rule['max'].' orang.']);
            }
        }

        return $rows;
    }

    public function sync(EventParticipant $participant, array $selection): void
    {
        $event = clone $participant->event;
        $event->personnel_requirements = $participant->personnel_requirements ?? [];
        $rows = $this->validate($event, (int) $participant->community_id, $selection);
        $participant->personnel()->delete();
        $participant->personnel()->createMany($rows);
    }
}
