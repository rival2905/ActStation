<?php

declare(strict_types=1);

use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    Model::reguard();
});

beforeEach(function (): void {
    Model::unguard();
    View::share('errors', new ViewErrorBag);
});

test('category form fields separate athlete and team scopes with chips and origin metadata', function (): void {
    $event = new Event([
        'title' => 'Kejuaraan Pelajar',
        'starts_at' => Carbon::now()->addDays(14),
        'registration_deadline' => Carbon::now()->addDays(7),
    ]);

    $docAthlete = new DocumentType([
        'id' => 1,
        'code' => 'id_card',
        'name' => 'KTP / Identitas Atlet',
        'scope' => 'athlete',
        'applicable_entities' => null,
    ]);

    $docSchoolTeam = new DocumentType([
        'id' => 2,
        'code' => 'school_team_letter',
        'name' => 'Surat Keterangan Sekolah untuk Tim',
        'scope' => 'team',
        'applicable_entities' => ['school'],
    ]);

    $docAssignment = new DocumentType([
        'id' => 3,
        'code' => 'team_assignment_letter',
        'name' => 'Surat Penugasan Tim',
        'scope' => 'team',
        'applicable_entities' => ['institution'],
    ]);

    $documentTypes = collect([$docAthlete, $docSchoolTeam, $docAssignment]);

    $view = $this->view('events.categories._form-fields', [
        'event' => $event,
        'category' => null,
        'fieldPrefix' => 'add_cat',
        'documentTypes' => $documentTypes,
    ]);

    $html = (string) $view;

    expect($html)
        ->toContain('Dokumen Wajib Per Atlet')
        ->toContain('Dokumen Wajib Per Tim')
        ->toContain('Surat Keterangan Sekolah untuk Tim')
        ->toContain('Surat Penugasan Tim')
        ->toContain('KTP / Identitas Atlet')
        ->toContain('data-scope="athlete"')
        ->toContain('data-scope="team"')
        ->toContain('data-applicable-entities="[&quot;school&quot;]"')
        ->toContain('Sekolah')
        ->toContain('Instansi');
});

test('category manager displays scope groups and warns explicitly on unknown metadata', function (): void {
    $event = new Event([
        'id' => 99,
        'title' => 'Open Cup',
        'slug' => 'open-cup',
        'user_id' => 1,
    ]);
    $event->exists = true;

    $category = new EventCategory([
        'id' => 50,
        'event_id' => 99,
        'name' => 'KU-18 Campuran',
        'match_type' => '5x5',
        'gender' => 'mixed',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'participants_count' => 0,
        'required_documents' => [
            'school_team_letter',
            'id_card',
            'missing_doc_code',
        ],
    ]);
    $category->exists = true;

    $docSchool = new DocumentType([
        'code' => 'school_team_letter',
        'name' => 'Surat Keterangan Sekolah untuk Tim',
        'scope' => 'team',
    ]);

    $docAthlete = new DocumentType([
        'code' => 'id_card',
        'name' => 'KTP Atlet',
        'scope' => 'athlete',
    ]);

    $documentTypes = collect([$docSchool, $docAthlete]);

    $view = $this->view('events.categories._manager', [
        'event' => $event,
        'categories' => collect([$category]),
        'documentTypes' => $documentTypes,
        'workspace' => 'organizer',
    ]);

    $html = (string) $view;

    expect($html)
        ->toContain('Dokumen Tim:')
        ->toContain('Surat Keterangan Sekolah untuk Tim')
        ->toContain('Dokumen Atlet:')
        ->toContain('KTP Atlet')
        ->toContain('Metadata Hilang / Tidak Dikenal:')
        ->toContain('missing_doc_code (Metadata tidak ditemukan)')
        ->not->toMatch('/Dokumen Tim:[^<]*missing_doc_code/');
});

test('registration view filters school letter out for institution while rendering for school in mixed category', function (): void {
    Storage::fake('local');
    Storage::fake('public');

    $event = new Event([
        'id' => 1,
        'title' => 'Turnamen Multi-Entitas',
        'slug' => 'turnamen-multi-entitas',
    ]);
    $event->exists = true;

    $mixedCategory = new EventCategory([
        'id' => 77,
        'event_id' => 1,
        'name' => 'KU-19 Umum Sekolah & Instansi',
        'match_type' => '5x5',
        'gender' => 'male',
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_fee' => 0,
        'registration_flow' => 'instant_free',
        'allow_community' => true,
        'allowed_community_subtypes' => ['school', 'institution'],
        'required_documents' => ['school_team_letter', 'team_mandate_letter', 'athlete_nisn'],
    ]);
    $mixedCategory->exists = true;
    $event->setRelation('categories', collect([$mixedCategory]));

    $docSchool = new DocumentType([
        'code' => 'school_team_letter',
        'name' => 'Surat Keterangan Sekolah untuk Tim',
        'scope' => 'team',
        'applicable_entities' => ['school'],
    ]);

    $docMandate = new DocumentType([
        'code' => 'team_mandate_letter',
        'name' => 'Surat Mandat / Perwakilan Tim',
        'scope' => 'team',
        'applicable_entities' => null,
    ]);

    $docNisn = new DocumentType([
        'code' => 'athlete_nisn',
        'name' => 'NISN Atlet',
        'scope' => 'athlete',
        'applicable_entities' => ['school'],
    ]);

    $documentTypes = collect([
        'school_team_letter' => $docSchool,
        'team_mandate_letter' => $docMandate,
        'athlete_nisn' => $docNisn,
    ]);

    // 1. Institution Registration
    $institutionComm = new Community([
        'id' => 10,
        'name' => 'PT Semen Padang',
        'subtype' => 'institution',
    ]);
    $institutionComm->exists = true;

    $athleteObj = (object) [
        'userId' => 501,
        'studentIdentifierType' => null,
        'studentIdentifierValue' => null,
        'name' => 'Pegawai Atlet',
        'gender' => 'male',
        'birthDate' => '2005-01-01',
    ];

    $instView = $this->view('panel.events.register', [
        'event' => $event,
        'community' => $institutionComm,
        'eligibleRosterByCategory' => [77 => collect([$athleteObj])],
        'documentTypes' => $documentTypes,
        'vaultDocuments' => collect(),
        'legalDocuments' => [],
        'registrations' => collect(),
        'registrationDrafts' => collect(),
        'maxTeamsPerEntity' => [],
    ]);

    $instHtml = (string) $instView;

    // Institution must have team_mandate_letter but NEVER school_team_letter
    expect($instHtml)
        ->toContain('team_doc_group_team_mandate_letter')
        ->not->toContain('team_doc_group_school_team_letter')
        ->not->toContain('name="team_documents[school_team_letter]"');

    // 2. School Registration
    $schoolComm = new Community([
        'id' => 20,
        'name' => 'SMAN 1 Bandung',
        'subtype' => 'school',
    ]);
    $schoolComm->exists = true;

    $schoolView = $this->view('panel.events.register', [
        'event' => $event,
        'community' => $schoolComm,
        'eligibleRosterByCategory' => [77 => collect([$athleteObj])],
        'documentTypes' => $documentTypes,
        'vaultDocuments' => collect(),
        'legalDocuments' => [],
        'registrations' => collect(),
        'registrationDrafts' => collect(),
        'maxTeamsPerEntity' => [],
    ]);

    $schoolHtml = (string) $schoolView;

    expect($schoolHtml)
        ->toContain('team_doc_group_school_team_letter')
        ->toContain('name="team_documents[school_team_letter]"')
        ->toContain('team_doc_group_team_mandate_letter');
});

test('registration athlete vault UI does not blindly treat rejected documents as valid', function (): void {
    Storage::fake('local');
    Storage::fake('public');

    $event = new Event([
        'id' => 1,
        'title' => 'Turnamen Vault Test',
        'slug' => 'turnamen-vault-test',
    ]);
    $event->exists = true;

    $category = new EventCategory([
        'id' => 88,
        'event_id' => 1,
        'name' => 'KU-16 Putra',
        'match_type' => '5x5',
        'gender' => 'male',
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_fee' => 0,
        'registration_flow' => 'instant_free',
        'allow_community' => true,
        'allowed_community_subtypes' => ['club'],
        'required_documents' => ['athlete_ktp'],
    ]);
    $category->exists = true;
    $event->setRelation('categories', collect([$category]));

    $docKtp = new DocumentType([
        'code' => 'athlete_ktp',
        'name' => 'KTP Atlet',
        'scope' => 'athlete',
        'applicable_entities' => null,
    ]);

    $documentTypes = collect(['athlete_ktp' => $docKtp]);

    $community = new Community([
        'id' => 30,
        'name' => 'Klub Mandiri',
        'subtype' => 'club',
    ]);
    $community->exists = true;

    $athlete1 = (object) ['userId' => 101, 'studentIdentifierType' => null, 'studentIdentifierValue' => null, 'name' => 'Atlet Terverifikasi', 'gender' => 'male', 'birthDate' => '2008-01-01'];
    $athlete2 = (object) ['userId' => 102, 'studentIdentifierType' => null, 'studentIdentifierValue' => null, 'name' => 'Atlet Ditolak', 'gender' => 'male', 'birthDate' => '2008-01-01'];

    // Put valid file in storage for athlete 1
    Storage::disk('local')->put('vault/ktp_101.pdf', 'dummy content');
    $validDoc = new Document([
        'category' => 'athlete_ktp',
        'status' => 'verified',
        'file_path' => 'vault/ktp_101.pdf',
    ]);

    // Rejected doc for athlete 2
    Storage::disk('local')->put('vault/ktp_102.pdf', 'dummy content');
    $rejectedDoc = new Document([
        'category' => 'athlete_ktp',
        'status' => 'rejected',
        'file_path' => 'vault/ktp_102.pdf',
    ]);

    $vaultDocuments = collect([
        101 => collect([$validDoc]),
        102 => collect([$rejectedDoc]),
    ]);

    $view = $this->view('panel.events.register', [
        'event' => $event,
        'community' => $community,
        'eligibleRosterByCategory' => [88 => collect([$athlete1, $athlete2])],
        'documentTypes' => $documentTypes,
        'vaultDocuments' => $vaultDocuments,
        'legalDocuments' => [],
        'registrations' => collect(),
        'registrationDrafts' => collect(),
        'maxTeamsPerEntity' => [],
    ]);

    $html = (string) $view;

    // Athlete 1 should show Terverifikasi
    expect($html)
        ->toContain('Terverifikasi')
        ->toContain('Ditolak (Unggah Ulang)')
        ->toContain('name="athlete_documents[102][athlete_ktp]"')
        ->not->toContain('vault/ktp_101.pdf')
        ->not->toContain('vault/ktp_102.pdf');
});
