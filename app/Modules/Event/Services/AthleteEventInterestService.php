<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\CommunityLookup;
use App\Contracts\DevelopmentLevelLookup;
use App\Contracts\DTOs\CommunitySummary;
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventInterest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AthleteEventInterestService
{
    public function __construct(private readonly CommunityLookup $communities) {}

    public function show(User $user, string $slug, ?string $search = null): array
    {
        abort_unless($user->is_active, 403);
        $event = $this->event($slug);

        return [
            'event' => $event,
            'communities' => array_values(array_filter($this->communities->athleteCommunities((int) $user->id), fn ($community) => $this->eligible($event, $user, $community))),
            'searchResults' => array_values(array_filter($this->communities->searchableCommunities($search ?? ''), fn ($community) => $this->eligible($event, $user, $community))),
            'existingInterests' => EventInterest::query()->where('user_id', $user->id)->where('event_id', $event->id)->get(),
        ];
    }

    public function store(User $user, string $slug, int $communityId): void
    {
        abort_unless($user->is_active, 403);
        DB::transaction(function () use ($user, $slug, $communityId): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->refresh();
            abort_unless($user->is_active, 403);
            $event = $this->event($slug);
            $community = collect($this->communities->athleteCommunities((int) $user->id))->firstWhere('id', $communityId);
            if (! $community || ! $this->eligible($event, $user, $community)) {
                throw ValidationException::withMessages(['community_id' => 'Pilih keanggotaan aktif yang sesuai persyaratan turnamen.']);
            }
            if (in_array((int) $event->id, app(AthleteEventWorkspace::class)->actionSummary((int) $user->id)['event_ids'], true)) {
                throw ValidationException::withMessages(['community_id' => 'Anda sudah masuk tim. Lihat Turnamen Saya.']);
            }
            EventInterest::firstOrCreate(['event_id' => $event->id, 'community_id' => $communityId, 'user_id' => $user->id]);
        });
    }

    public function forUserEventIds(int $userId): array
    {
        return EventInterest::query()->where('user_id', $userId)->pluck('event_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    public function forManager(int $userId, int $communityId): LengthAwarePaginator
    {
        abort_unless($this->communities->canManage($userId, $communityId), 403);

        return EventInterest::query()->where('community_id', $communityId)
            ->whereIn('user_id', $this->communities->activeAthleteIds($communityId))
            ->whereHas('event', fn ($query) => $query->where('status', 'published'))
            ->with(['event', 'user:id,name'])->latest('id')->paginate(20, ['*'], 'interests_page');
    }

    public function event(string $slug): Event
    {
        return Event::query()->where('slug', $slug)->where('status', 'published')->where('registration_open', true)
            ->where(fn ($query) => $query->whereNull('registration_deadline')->orWhere('registration_deadline', '>=', now()))
            ->with('categories')->firstOrFail();
    }

    private function eligible(Event $event, User $user, CommunitySummary $community): bool
    {
        if ($community->subtype !== 'school' && $community->sport_category_id !== null && $community->sport_category_id !== (int) $event->sport_category_id) {
            return false;
        }

        return $event->categories->contains(function (EventCategory $category) use ($event, $user, $community): bool {
            if ($category->registration_flow === 'by_invitation'
                || ! $category->acceptsCommunityType($community->subtype, $community->institution_type, $community->school_level)
                || ! $category->acceptsAthleteGender($user->gender)) {
                return false;
            }
            if ($category->min_age !== null || $category->max_age !== null) {
                if (! $user->birth_date || ! $event->starts_at || $user->birth_date->greaterThan($event->starts_at)) {
                    return false;
                }
                $age = (int) $user->birth_date->diffInYears($event->starts_at);

                return ($category->min_age === null || $age >= $category->min_age)
                    && ($category->max_age === null || $age <= $category->max_age);
            }

            if ($category->development_level_id !== null) {
                return $user->birth_date !== null
                    && app(DevelopmentLevelLookup::class)->findForBirthDate($user->birth_date, (int) $event->sport_category_id)?->id === (int) $category->development_level_id;
            }

            return true;
        });
    }
}
