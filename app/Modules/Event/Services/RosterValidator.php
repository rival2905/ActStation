<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\DevelopmentLevelLookup;
use App\Contracts\DTOs\EligibleAthleteDto;
use App\Contracts\RosterProvider;
use App\Models\User;
use App\Modules\Event\Exceptions\RosterValidationException;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\Game;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RosterValidator
{
    public function __construct(
        private readonly RosterProvider $rosterProvider,
        private readonly DevelopmentLevelLookup $developmentLevelLookup,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $members
     *
     * @throws RosterValidationException
     */
    public function validate(EventParticipant $participant, array $members): void
    {
        $category = $participant->category ?? EventCategory::query()->findOrFail($participant->event_category_id);
        $event = $participant->event ?? $category->event;

        // 1. Kunci roster: Ditolak jika kategori sudah memiliki game finished
        $hasFinishedGame = Game::query()
            ->where('event_category_id', $category->id)
            ->where('game_status', 'finished')
            ->exists();

        if ($hasFinishedGame) {
            throw new RosterValidationException('Roster terkunci karena pertandingan di kategori ini sudah ada yang selesai.');
        }

        $errors = [];
        $memberCount = count($members);

        // 2. Batas min/max roster
        if ($category->min_roster !== null && $memberCount < $category->min_roster) {
            $errors['roster'][] = "Jumlah anggota roster minimal {$category->min_roster} (saat ini: {$memberCount}).";
        }

        if ($category->max_roster !== null && $memberCount > $category->max_roster) {
            $errors['roster'][] = "Jumlah anggota roster maksimal {$category->max_roster} (saat ini: {$memberCount}).";
        }

        // 3. Tepat satu kapten
        $captainCount = 0;
        foreach ($members as $member) {
            if (! empty($member['is_captain'])) {
                $captainCount++;
            }
        }

        if ($captainCount !== 1) {
            $errors['captain'][] = $captainCount === 0
                ? 'Roster harus memiliki tepat satu kapten (saat ini tidak ada).'
                : "Roster harus memiliki tepat satu kapten (saat ini ada {$captainCount} kapten).";
        }

        // 4. Jersey unik per tim
        $seenJerseys = [];
        foreach ($members as $index => $member) {
            $jersey = $member['jersey_number'] ?? null;
            if ($jersey !== null && $jersey !== '') {
                $jerseyInt = (int) $jersey;
                if (in_array($jerseyInt, $seenJerseys, true)) {
                    $errors["members.{$index}.jersey_number"][] = "Nomor punggung {$jerseyInt} duplikat dalam tim.";
                } else {
                    $seenJerseys[] = $jerseyInt;
                }
            }
        }

        // 5. Cek duplikasi atlet dalam input roster sendiri
        $submittedUserIds = [];
        foreach ($members as $index => $member) {
            $userId = isset($member['user_id']) ? (int) $member['user_id'] : 0;
            if ($userId <= 0) {
                $errors["members.{$index}.user_id"][] = 'ID atlet wajib diisi.';

                continue;
            }

            if (in_array($userId, $submittedUserIds, true)) {
                $errors["members.{$index}.user_id"][] = "Atlet [{$userId}] terdaftar ganda di roster ini.";
            } else {
                $submittedUserIds[] = $userId;
            }
        }

        // 6. Satu atlet tidak boleh di dua tim pada kategori yang sama
        if (! empty($submittedUserIds)) {
            $crossTeamUserIds = DB::table('event_participant_members')
                ->join('event_participants', 'event_participant_members.event_participant_id', '=', 'event_participants.id')
                ->where('event_participants.event_category_id', $category->id)
                ->where('event_participants.id', '!=', $participant->id)
                ->whereIn('event_participant_members.user_id', $submittedUserIds)
                ->pluck('event_participant_members.user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! empty($crossTeamUserIds)) {
                foreach ($members as $index => $member) {
                    $userId = (int) ($member['user_id'] ?? 0);
                    if (in_array($userId, $crossTeamUserIds, true)) {
                        $errors["members.{$index}.user_id"][] = "Atlet [{$userId}] sudah terdaftar di tim lain pada kategori yang sama.";
                    }
                }
            }
        }

        // 7. Ambil atlet eligible dari RosterProvider atau validasi individual
        if ($participant->community_id === null) {
            if ($participant->registrant_type === 'individual') {
                foreach ($members as $index => $member) {
                    $userId = (int) ($member['user_id'] ?? 0);
                    if ($userId <= 0) {
                        continue;
                    }

                    $athleteUser = User::query()->find($userId);
                    if (! $athleteUser) {
                        $errors["members.{$index}.user_id"][] = "User [{$userId}] tidak ditemukan.";

                        continue;
                    }

                    if ($category->gender !== 'mixed') {
                        if ($athleteUser->gender !== null && $athleteUser->gender !== $category->gender) {
                            $errors["members.{$index}.gender"][] = "Gender atlet ({$athleteUser->gender}) tidak sesuai dengan kategori ({$category->gender}).";
                        }
                    }

                    if ($category->min_age !== null || $category->max_age !== null) {
                        $this->validateAgeAtEvent($athleteUser->birth_date, $event?->starts_at, $category, $index, $userId, $errors);
                    } elseif ($category->development_level_id !== null) {
                        if ($athleteUser->birth_date === null) {
                            $errors["members.{$index}.birth_date"][] = "Tanggal lahir atlet [{$userId}] tidak terdata untuk verifikasi umur.";
                        } else {
                            $levelSummary = $this->developmentLevelLookup->findForBirthDate(
                                $athleteUser->birth_date,
                                $event?->sport_category_id ? (int) $event->sport_category_id : null
                            );

                            if ($levelSummary === null || $levelSummary->id !== (int) $category->development_level_id) {
                                $errors["members.{$index}.age"][] = "Umur atlet [{$userId}] berada di luar jenjang pembinaan kategori.";
                            }
                        }
                    }
                }
            } else {
                $errors['community'][] = 'Peserta harus terasosiasi dengan komunitas untuk validasi roster atlet.';
            }
        } else {
            /** @var Collection<int, EligibleAthleteDto> $eligibleAthletes */
            $eligibleAthletes = $this->rosterProvider->eligibleAthletes(
                (int) $participant->community_id,
                (int) $category->id
            );

            $eligibleMap = $eligibleAthletes->keyBy('userId');

            foreach ($members as $index => $member) {
                $userId = (int) ($member['user_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                /** @var ?EligibleAthleteDto $athlete */
                $athlete = $eligibleMap->get($userId);
                if (! $athlete) {
                    $errors["members.{$index}.user_id"][] = "Atlet [{$userId}] tidak memenuhi syarat keanggotaan aktif komunitas.";

                    continue;
                }

                // 8. Gender atlet cocok dengan category.gender (kecuali gender === 'mixed')
                if ($category->gender !== 'mixed') {
                    if ($athlete->gender !== null && $athlete->gender !== $category->gender) {
                        $errors["members.{$index}.gender"][] = "Gender atlet ({$athlete->gender}) tidak sesuai dengan kategori ({$category->gender}).";
                    }
                }

                // 9. Batas usia kategori dihitung tepat pada tanggal mulai event.
                if ($category->min_age !== null || $category->max_age !== null) {
                    $this->validateAgeAtEvent($athlete->birthDate, $event?->starts_at, $category, $index, $userId, $errors);
                } elseif ($category->development_level_id !== null) {
                    if ($athlete->birthDate === null) {
                        $errors["members.{$index}.birth_date"][] = "Tanggal lahir atlet [{$userId}] tidak terdata untuk verifikasi umur.";
                    } else {
                        $levelSummary = $this->developmentLevelLookup->findForBirthDate(
                            $athlete->birthDate,
                            $event?->sport_category_id ? (int) $event->sport_category_id : null
                        );

                        if ($levelSummary === null || $levelSummary->id !== (int) $category->development_level_id) {
                            $errors["members.{$index}.age"][] = "Umur atlet [{$userId}] berada di luar jenjang pembinaan kategori.";
                        }
                    }
                }
            }
        }

        if (! empty($errors)) {
            throw new RosterValidationException('Validasi roster gagal.', $errors);
        }
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateAgeAtEvent(mixed $birthDate, mixed $startsAt, EventCategory $category, int $index, int $userId, array &$errors): void
    {
        if ($startsAt === null) {
            $errors["members.{$index}.age"][] = 'Tanggal mulai event wajib diisi untuk memvalidasi batas usia kategori.';

            return;
        }

        if ($birthDate === null) {
            $errors["members.{$index}.birth_date"][] = "Tanggal lahir atlet [{$userId}] tidak terdata untuk verifikasi umur.";

            return;
        }

        $birth = Carbon::parse($birthDate)->startOfDay();
        $eventDate = Carbon::parse($startsAt)->startOfDay();
        if ($birth->greaterThan($eventDate)) {
            $errors["members.{$index}.birth_date"][] = "Tanggal lahir atlet [{$userId}] tidak boleh sesudah tanggal mulai event.";

            return;
        }

        $age = (int) $birth->diffInYears($eventDate);
        if ($category->min_age !== null && $age < (int) $category->min_age) {
            $errors["members.{$index}.age"][] = "Umur atlet [{$userId}] pada hari event ({$age} tahun) kurang dari batas minimum {$category->min_age} tahun.";
        }

        if ($category->max_age !== null && $age > (int) $category->max_age) {
            $errors["members.{$index}.age"][] = "Umur atlet [{$userId}] pada hari event ({$age} tahun) melebihi batas maksimum {$category->max_age} tahun.";
        }
    }
}
