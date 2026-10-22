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
use Illuminate\Support\Str;

class GenerateDraftTeamsSeeder extends Seeder
{
    public function run(int $eventId = 1): void
    {
        $event = Event::with('categories')->findOrFail($eventId);
        $owner = User::findOrFail($event->user_id ?? 3);

        $smaSchools = [
            ['npsn' => '20206208', 'name' => 'SMAN 1 PADALARANG', 'level' => 'sma'],
            ['npsn' => '20227910', 'name' => 'SMAN 2 PADALARANG', 'level' => 'sma'],
            ['npsn' => '20232375', 'name' => 'SMAN 1 CISARUA', 'level' => 'sma'],
            ['npsn' => '20227901', 'name' => 'SMAN 1 PARONGPONG', 'level' => 'sma'],
            ['npsn' => '20206146', 'name' => 'SMAN 1 CILILIN', 'level' => 'sma'],
            ['npsn' => '20227902', 'name' => 'SMAN 1 BATUJAJAR', 'level' => 'sma'],
            ['npsn' => '20206211', 'name' => 'SMA NEGERI 1 LEMBANG', 'level' => 'sma'],
            ['npsn' => '69965194', 'name' => 'SMA NEGERI 2 LEMBANG', 'level' => 'sma'],
        ];

        $smpSchools = [
            ['npsn' => '20206032', 'name' => 'SMP NEGERI 1 PADALARANG', 'level' => 'smp'],
            ['npsn' => '20206081', 'name' => 'SMP NEGERI 2 PADALARANG', 'level' => 'smp'],
            ['npsn' => '20252478', 'name' => 'SMP NEGERI 3 PADALARANG', 'level' => 'smp'],
            ['npsn' => '20229812', 'name' => 'SMP NEGERI 4 PADALARANG', 'level' => 'smp'],
            ['npsn' => '20279861', 'name' => 'SMP NEGERI 5 PADALARANG', 'level' => 'smp'],
            ['npsn' => '20279913', 'name' => 'SMP KARYA PRESTASI MANDIRI PADALARANG', 'level' => 'smp'],
            ['npsn' => '20227684', 'name' => 'SMP KRIDA UTAMA PADALARANG', 'level' => 'smp'],
            ['npsn' => '20268729', 'name' => 'SMP MARGA UTAMA PADALARANG', 'level' => 'smp'],
        ];

        $institutions = [
            ['name' => 'Dispora Kabupaten Bandung Barat', 'type' => 'government', 'slug' => 'dispora-kbb'],
            ['name' => 'Dinas Pendidikan KBB', 'type' => 'government', 'slug' => 'disdik-kbb'],
            ['name' => 'Dinas Perhubungan KBB', 'type' => 'government', 'slug' => 'dishub-kbb'],
            ['name' => 'Sekretariat Daerah KBB', 'type' => 'government', 'slug' => 'setda-kbb'],
            ['name' => 'Bapenda Kabupaten Bandung Barat', 'type' => 'government', 'slug' => 'bapenda-kbb'],
            ['name' => 'RSUD Cililin KBB', 'type' => 'government', 'slug' => 'rsud-cililin'],
            ['name' => 'Bank BJB Cabang Padalarang', 'type' => 'corporate', 'slug' => 'bjb-padalarang'],
            ['name' => 'PTPN VIII Bandung Barat', 'type' => 'corporate', 'slug' => 'ptpn-viii-kbb'],
        ];

        $categories = $event->categories->keyBy('id');

        // Cat 1: Instansi / Dinas Putra (KU 30++)
        $cat1 = $categories->get(1);
        if ($cat1) {
            $this->command->info("Membuat 8 draft untuk Kategori: {$cat1->name}");
            $this->seedCategoryDrafts($event, $cat1, $owner, $institutions, 'institution', 'male', '1988-06-12', 8);
        }

        // Cat 2: Pelajar SMA Putra
        $cat2 = $categories->get(2);
        if ($cat2) {
            $this->command->info("Membuat 8 draft untuk Kategori: {$cat2->name}");
            $this->seedCategoryDrafts($event, $cat2, $owner, $smaSchools, 'school', 'male', '2009-04-10', 8);
        }

        // Cat 3: Pelajar SMA Putri
        $cat3 = $categories->get(3);
        if ($cat3) {
            $this->command->info("Membuat 8 draft untuk Kategori: {$cat3->name}");
            $this->seedCategoryDrafts($event, $cat3, $owner, $smaSchools, 'school', 'female', '2009-08-20', 8);
        }

        // Cat 4: Pelajar SMP Putra
        $cat4 = $categories->get(4);
        if ($cat4) {
            $this->command->info("Membuat 8 draft untuk Kategori: {$cat4->name}");
            $this->seedCategoryDrafts($event, $cat4, $owner, $smpSchools, 'school', 'male', '2012-03-15', 8);
        }

        // Cat 5: Pelajar SMP Putri
        $cat5 = $categories->get(5);
        if ($cat5) {
            $this->command->info("Membuat 8 draft untuk Kategori: {$cat5->name}");
            $this->seedCategoryDrafts($event, $cat5, $owner, $smpSchools, 'school', 'female', '2012-09-05', 8);
        }

        $this->command->info('Selesai! Seluruh draft 8 tim per kategori berhasil dibuat.');
    }

    private function seedCategoryDrafts(
        Event $event,
        EventCategory $category,
        User $owner,
        array $entities,
        string $subtype,
        string $gender,
        string $birthDate,
        int $playerCount = 8
    ): void {
        $types = DocumentType::whereIn('code', $category->required_documents ?? [])
            ->get()
            ->filter(fn ($d) => $d->appliesToEntity($subtype));

        $positions = $gender === 'female'
            ? ['Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center']
            : ['PG', 'SG', 'SF', 'PF', 'C'];

        $maleNames = ['Rizky', 'Bintang', 'Dimas', 'Satria', 'Aditya', 'Fajar', 'Bagas', 'Ilham', 'Arya', 'Galih', 'Daffa', 'Rafi'];
        $femaleNames = ['Aulia', 'Nabila', 'Siti', 'Putri', 'Zahra', 'Anisa', 'Tiara', 'Salma', 'Rania', 'Dewi', 'Cantika', 'Syifa'];

        foreach ($entities as $idx => $entityInfo) {
            $teamNumber = $idx + 1;

            // Check if draft already exists for this community & category
            if ($subtype === 'school') {
                $npsn = $entityInfo['npsn'];
                $communityName = $entityInfo['name'];
                $slug = Str::slug($communityName);

                $community = Community::firstOrCreate(
                    ['npsn' => $npsn],
                    [
                        'name' => $communityName,
                        'slug' => $slug,
                        'subtype' => 'school',
                        'sport_category_id' => $event->sport_category_id,
                        'description' => 'Komunitas resmi '.$communityName,
                        'status' => 'verified',
                        'is_active' => true,
                        'province_id' => '32',
                        'city_id' => '32.17',
                        'address' => 'Kabupaten Bandung Barat',
                        'contact_person' => 'Pembina Olahraga',
                        'contact_phone' => '0812345678'.str_pad((string) $teamNumber, 2, '0', STR_PAD_LEFT),
                        'contact_email' => Str::slug($communityName).'@sekolah.sch.id',
                    ]
                );
            } else {
                $communityName = $entityInfo['name'];
                $slug = $entityInfo['slug'];
                $institutionType = $entityInfo['type'];

                $community = Community::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $communityName,
                        'subtype' => 'institution',
                        'institution_type' => $institutionType,
                        'sport_category_id' => $event->sport_category_id,
                        'description' => 'Instansi resmi '.$communityName,
                        'status' => 'verified',
                        'is_active' => true,
                        'province_id' => '32',
                        'city_id' => '32.17',
                        'address' => 'Komplek Perkantoran Pemkab Bandung Barat',
                        'contact_person' => 'Ketua Kontingen',
                        'contact_phone' => '0813998877'.str_pad((string) $teamNumber, 2, '0', STR_PAD_LEFT),
                        'contact_email' => $slug.'@instansi.go.id',
                    ]
                );
            }

            // Ensure owner is community official
            CommunityOfficial::firstOrCreate(
                ['community_id' => $community->id, 'user_id' => $owner->id],
                [
                    'position' => 'Manajer Tim',
                    'status' => 'active',
                    'started_at' => today(),
                ]
            );

            // Check if draft already exists
            $existingDraft = RegistrationDraft::where('community_id', $community->id)
                ->where('event_category_id', $category->id)
                ->where('event_id', $event->id)
                ->first();

            if ($existingDraft) {
                $this->command->line("  - [SKIP] Draft sudah ada untuk {$community->name} di {$category->name}");

                continue;
            }

            // Create players
            $members = [];
            $firstNames = ($gender === 'female') ? $femaleNames : $maleNames;

            for ($p = 1; $p <= $playerCount; $p++) {
                $firstName = $firstNames[($p + $idx) % count($firstNames)];
                $fullName = $firstName.' '.chr(65 + $idx).' '.($subtype === 'school' ? 'Siswa' : 'Karyawan').' '.$p;
                $email = Str::slug($communityName)."-cat{$category->id}-p{$p}@actstation.test";

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $fullName,
                        'password' => bcrypt('password123'),
                        'is_active' => true,
                        'gender' => ($category->gender === 'mixed' ? ($p % 2 === 0 ? 'female' : 'male') : $gender),
                        'birth_date' => $birthDate,
                        'birth_place' => 'Bandung Barat',
                        'phone_number' => '08571122'.str_pad((string) ($idx * 10 + $p), 4, '0', STR_PAD_LEFT),
                    ]
                );

                if (! $user->hasRole('athlete')) {
                    $user->assignRole('athlete');
                }

                $identifierValue = sprintf('%02d%04d%02d%02d', $category->id, $community->id, $idx, $p);
                $identifierType = $subtype === 'school' ? 'nisn' : 'nik';

                CommunityAthlete::firstOrCreate(
                    ['community_id' => $community->id, 'user_id' => $user->id],
                    [
                        'nisn' => ($subtype === 'school' ? $identifierValue : null),
                        'status' => 'active',
                        'region_status' => 'verified',
                        'joined_at' => today(),
                    ]
                );

                if ($subtype === 'school') {
                    UserEducation::firstOrCreate(
                        ['user_id' => $user->id, 'community_id' => $community->id],
                        [
                            'institution_name' => $community->name,
                            'education_type' => 'school',
                            'identifier_type' => 'nisn',
                            'identifier_value' => $identifierValue,
                            'education_detail' => ($entityInfo['level'] === 'sma' ? 'Kelas XI' : 'Kelas VIII'),
                        ]
                    );
                }

                $members[] = [
                    'user_id' => $user->id,
                    'jersey_number' => ($p * 2 + 1) % 99,
                    'is_captain' => ($p === 1 ? 1 : 0),
                    'position' => $positions[($p - 1) % count($positions)],
                    'team_role' => 'player',
                ];
            }

            $teamName = $community->name.' — '.$category->name;

            $payload = [
                'event_category_id' => $category->id,
                'registration_source' => 'community',
                'community_subtype' => $subtype,
                'team_name' => $teamName,
                'contact_person' => $community->contact_person,
                'contact_phone' => $community->contact_phone,
                'notes' => 'Draft tim pengujian otomatis ActStation untuk kategori '.$category->name,
                'members' => $members,
            ];

            // Generate synthetic upload documents
            $uploads = ['team_documents' => [], 'athlete_documents' => []];
            $tempFiles = [];

            try {
                foreach ($types as $type) {
                    $targetUids = ($type->scope === 'team') ? ['team'] : array_column($members, 'user_id');
                    foreach ($targetUids as $uid) {
                        $path = tempnam(sys_get_temp_dir(), 'draft_doc_');
                        $tempFiles[] = $path;

                        $img = imagecreatetruecolor(500, 120);
                        imagefill($img, 0, 0, imagecolorallocate($img, 245, 247, 250));
                        $textColor = imagecolorallocate($img, 30, 41, 59);
                        $headerColor = imagecolorallocate($img, 2, 132, 199);

                        imagestring($img, 4, 15, 15, 'DOKUMEN DRAFT REGISTRASI ACTSTATION', $headerColor);
                        imagestring($img, 3, 15, 45, 'Tim: '.substr($teamName, 0, 40), $textColor);
                        imagestring($img, 2, 15, 75, 'Jenis: '.$type->name.' ('.$type->code.')', $textColor);
                        imagepng($img, $path);
                        imagedestroy($img);

                        $uploadedFile = new UploadedFile($path, 'DOC-'.$type->code.'.png', 'image/png', null, true);

                        if ($uid === 'team') {
                            $uploads['team_documents'][$type->code] = $uploadedFile;
                        } else {
                            $uploads['athlete_documents'][$uid][$type->code] = $uploadedFile;
                        }
                    }
                }

                $request = Request::create('/', 'POST', $payload, [], $uploads);
                $draft = app(RegistrationDraftService::class)->createDraft($owner, $community->id, $event, $payload, $request);

                $this->command->line("  ✓ [OK] Tim {$teamNumber}/8: {$teamName} (Draft ID: {$draft->id}) — {$playerCount} Pemain");
            } finally {
                foreach ($tempFiles as $f) {
                    @unlink($f);
                }
            }
        }
    }
}
