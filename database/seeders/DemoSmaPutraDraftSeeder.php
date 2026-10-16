<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserEducation;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\Event\Services\RegistrationDraftService;
use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoSmaPutraDraftSeeder extends Seeder
{
    public function run(int $eventId = 1, ?int $categoryId = null): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Data DEMO hanya untuk lokal/pengujian.');
        Http::preventStrayRequests();
        $event = Event::findOrFail($eventId);
        $category = EventCategory::where('event_id', $eventId)->when($categoryId, fn ($q) => $q->whereKey($categoryId))
            ->when(! $categoryId, fn ($q) => $q->where('slug', 'sma-putra'))->firstOrFail();
        $owner = User::findOrFail($event->user_id);
        $types = DocumentType::whereIn('code', $category->required_documents ?? [])->get()->filter(fn ($d) => $d->appliesToEntity('school'));
        $count = max(8, (int) $category->min_roster);
        abort_if($count > (int) $category->max_roster || $category->gender !== 'male', 422);
        $birthDate = $event->starts_at->copy()->subYears(max(17, (int) $category->min_age))->toDateString();
        $createdPaths = [];
        try {
            DB::transaction(function () use ($event, $category, $owner, $types, $count, $birthDate, &$createdPaths): void {
                Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                for ($team = 1; $team <= 8; $team++) {
                    $tag = sprintf('demo-e%d-sma-putra-%02d', $event->id, $team);
                    $school = Community::where('slug', $tag)->first();
                    if ($school) {
                        abort_unless($school->description === 'DATA DEMO SINTETIS - BUKAN SEKOLAH NYATA', 409);
                        abort_unless(RegistrationDraft::where('community_id', $school->id)->where('event_id', $event->id)->exists(), 409, 'Data demo parsial; periksa sebelum melanjutkan.');

                        continue;
                    }
                    $name = sprintf('DEMO SMA Putra %02d', $team);
                    $npsn = sprintf('D%05d%02d', $event->id, $team);
                    DB::table('schools')->insert(['npsn' => $npsn, 'name' => $name, 'school_level' => 'sma', 'school_status' => 'swasta', 'source' => 'demo', 'created_at' => now(), 'updated_at' => now()]);
                    $school = Community::create(['name' => $name, 'slug' => $tag, 'npsn' => $npsn, 'subtype' => 'school', 'sport_category_id' => $event->sport_category_id, 'description' => 'DATA DEMO SINTETIS - BUKAN SEKOLAH NYATA', 'status' => 'verified', 'is_active' => true, 'verified_at' => now(), 'registered_at' => now()]);
                    CommunityOfficial::create(['community_id' => $school->id, 'user_id' => $owner->id, 'position' => 'Pengurus DEMO', 'status' => 'active', 'started_at' => today(), 'ended_at' => null]);
                    $members = [];
                    for ($player = 1; $player <= $count; $player++) {
                        $user = User::create(['name' => sprintf('DEMO Pemain %02d-%02d', $team, $player), 'email' => $tag.'-p'.$player.'@example.invalid', 'password' => Str::random(64), 'is_active' => true, 'gender' => 'male', 'birth_date' => $birthDate, 'birth_place' => 'Kota DEMO']);
                        $user->assignRole('athlete');
                        $nisn = sprintf('00%04d%02d%02d', $event->id, $team, $player);
                        CommunityAthlete::create(['community_id' => $school->id, 'user_id' => $user->id, 'development_level_id' => $category->development_level_id, 'nisn' => $nisn, 'initiated_by' => 'community', 'status' => 'active', 'region_status' => 'pending', 'joined_at' => today(), 'responded_at' => now(), 'left_at' => null]);
                        UserEducation::create(['user_id' => $user->id, 'community_id' => $school->id, 'institution_name' => $name, 'education_type' => 'school', 'identifier_type' => 'nisn', 'identifier_value' => $nisn, 'education_detail' => 'Kelas XI DEMO']);
                        $members[] = ['user_id' => $user->id, 'jersey_number' => $player + 3, 'is_captain' => $player === 1, 'position' => ['PG', 'SG', 'SF', 'PF', 'C'][($player - 1) % 5], 'team_role' => 'player'];
                    }
                    $payload = ['event_category_id' => $category->id, 'registration_source' => 'community', 'community_subtype' => 'school', 'team_name' => $name, 'contact_person' => 'Pengurus DEMO', 'contact_phone' => '000000000000', 'notes' => 'DATA DEMO: roster dan berkas sintetis untuk uji kirim; bukan pendaftaran nyata.', 'members' => $members];
                    $uploads = ['team_documents' => [], 'athlete_documents' => []];
                    $temporary = [];
                    try {
                        foreach ($types as $type) {
                            foreach ($type->scope === 'team' ? ['team'] : array_column($members, 'user_id') as $uid) {
                                $path = tempnam(getenv('TMPDIR') ?: sys_get_temp_dir(), 'demo_');
                                $temporary[] = $path;
                                $image = imagecreatetruecolor(900, 180);
                                imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
                                $ink = imagecolorallocate($image, 180, 0, 0);
                                imagestring($image, 5, 20, 25, 'DEMO - BUKAN DOKUMEN SAH', $ink);
                                imagestring($image, 4, 20, 70, $name.' / '.$uid.' / '.$type->code, $ink);
                                imagestring($image, 4, 20, 110, 'DATA SINTETIS UNTUK PENGUJIAN ACTSTATION', $ink);
                                imagepng($image, $path);
                                $file = new UploadedFile($path, 'DEMO-'.$type->code.'.png', 'image/png', null, true);
                                if ($uid === 'team') {
                                    $uploads['team_documents'][$type->code] = $file;
                                } else {
                                    $uploads['athlete_documents'][$uid][$type->code] = $file;
                                }
                            }
                        }
                        $request = Request::create('/', 'POST', $payload, [], $uploads);
                        $draft = app(RegistrationDraftService::class)->createDraft($owner, $school->id, $event, $payload, $request);
                        foreach ($draft->files['team_documents'] as $meta) {
                            $createdPaths[] = $meta['staged_path'];
                        }
                        foreach ($draft->files['athlete_documents'] as $documents) {
                            foreach ($documents as $meta) {
                                $createdPaths[] = $meta['staged_path'];
                            }
                        }
                    } finally {
                        foreach ($temporary as $path) {
                            @unlink($path);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($createdPaths);
            throw $e;
        }
    }
}
