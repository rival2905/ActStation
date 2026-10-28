<?php

declare(strict_types=1);

use App\Contracts\CommunityLookup;
use App\Contracts\DTOs\CommunitySummary;
use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\ActivityMember;
use App\Modules\Event\Models\ActivityRegistration;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventActivity;
use App\Modules\Event\Models\EventAnswer;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\EventQuestion;
use App\Modules\Event\Services\EventQuestionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
});

test('draft question validation permits incomplete saves but enforces active required branches on completion', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $service = app(EventQuestionService::class);
    $parent = $service->save($event, $owner, ['target' => 'team', 'type' => 'select', 'label' => 'Kesenian?', 'options' => ['Ya', 'Tidak'], 'is_required' => true, 'order' => 1]);
    $child = $service->save($event, $owner, ['target' => 'team', 'type' => 'text', 'label' => 'Nama', 'is_required' => true, 'depends_on_question_id' => $parent->id, 'conditional_value' => 'Ya', 'order' => 2]);
    $questions = EventQuestion::where('event_id', $event->id)->orderBy('order')->get();
    $created = [];
    expect($service->cleanAnswers($event, $questions, [], [], false, $created))->toBe([]);
    expect(fn () => $service->assertAnswersComplete($event, $questions, [$parent->id => 'Ya']))->toThrow(ValidationException::class);
    $service->assertAnswersComplete($event, $questions, [$parent->id => 'Tidak']);
    expect($service->cleanAnswers($event, $questions, [$parent->id => 'Tidak', $child->id => 'Ignored'], [], true, $created))->toBe([$parent->id => 'Tidak']);
});

test('question definitions enforce target and earlier conditional parent choices', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $service = app(EventQuestionService::class);
    $question = $service->save($event, $owner, ['target' => 'institution', 'type' => 'select', 'label' => 'Memiliki ekskul tari?', 'options' => ['Ya', 'Tidak'], 'is_required' => true, 'order' => 1]);
    $child = $service->save($event, $owner, ['target' => 'institution', 'type' => 'text', 'label' => 'Nama ekskul', 'depends_on_question_id' => $question->id, 'conditional_value' => 'Ya', 'order' => 2]);
    expect($child->depends_on_question_id)->toBe($question->id);
    expect(fn () => $service->save($event, $owner, ['target' => 'team', 'type' => 'text', 'label' => 'Salah sasaran', 'depends_on_question_id' => $question->id, 'conditional_value' => 'Ya', 'order' => 3]))->toThrow(ValidationException::class);
});

test('answers are encrypted in database and decrypted upon retrieval', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $service = app(EventQuestionService::class);
    $question = $service->save($event, $owner, [
        'target' => 'team',
        'type' => 'text',
        'label' => 'Catatan Khusus Medis',
        'is_required' => true,
        'order' => 1,
    ]);

    $team = EventParticipant::factory()->create([
        'event_id' => $event->id,
        'user_id' => $owner->id,
        'community_id' => null,
    ]);

    $secretAnswer = 'Riwayat Asma Kronis Atlet #12';
    $service->submit($event, $owner, 'team', $team->id, [
        (string) $question->id => $secretAnswer,
    ]);

    $raw = DB::table('event_answers')
        ->where('event_id', $event->id)
        ->where('target', 'team')
        ->where('subject_id', $team->id)
        ->value('answers');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toContain($secretAnswer);

    $answer = EventAnswer::where('event_id', $event->id)
        ->where('target', 'team')
        ->where('subject_id', $team->id)
        ->first();

    expect($answer->answers[(string) $question->id])->toBe($secretAnswer);

    $form = $service->form($event, $owner, 'team', $team->id);
    expect($form['answers'][(string) $question->id])->toBe($secretAnswer);
});

test('school arts question asks opening willingness only after yes without duplicate team answers', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $school = Community::factory()->create(['subtype' => 'school', 'status' => 'verified']);
    $club = Community::factory()->create(['subtype' => 'club', 'status' => 'verified']);
    foreach ([$school, $club] as $community) {
        CommunityOfficial::factory()->create(['community_id' => $community->id, 'user_id' => $owner->id, 'status' => 'active', 'ended_at' => null]);
    }
    $service = app(EventQuestionService::class);
    $common = ['target' => 'institution', 'type' => 'select', 'options' => ['Ya', 'Tidak'], 'institution_types' => ['school'], 'is_required' => false];
    $parent = $service->save($event, $owner, $common + ['label' => 'Apakah sekolah memiliki kelompok atau ekstrakurikuler kesenian?', 'order' => 1]);
    $child = $service->save($event, $owner, $common + ['label' => 'Apakah bersedia tampil pada pembukaan?', 'order' => 2, 'depends_on_question_id' => $parent->id, 'conditional_value' => 'Ya']);
    expect($service->form($event, $owner, 'institution', $school->id)['questions'])->toHaveCount(2);
    expect($service->form($event, $owner, 'institution', $club->id)['questions'])->toBe([]);
    $service->submit($event, $owner, 'institution', $school->id, [$parent->id => 'Tidak', $child->id => 'Ya']);
    expect($service->form($event, $owner, 'institution', $school->id)['answers'])->toBe([$parent->id => 'Tidak']);
    $service->submit($event, $owner, 'institution', $school->id, [$parent->id => 'Ya', $child->id => 'Ya']);
    expect($service->form($event, $owner, 'institution', $school->id)['answers'])->toBe([$parent->id => 'Ya', $child->id => 'Ya']);
    expect(EventAnswer::where('event_id', $event->id)->where('target', 'institution')->where('subject_id', $school->id)->count())->toBe(1);
});

test('branching ignores hidden child when condition not met and requires child when condition met', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $service = app(EventQuestionService::class);

    $parent = $service->save($event, $owner, [
        'target' => 'team',
        'type' => 'select',
        'label' => 'Perlu Transportasi Bus?',
        'options' => ['Ya', 'Tidak'],
        'is_required' => true,
        'order' => 1,
    ]);

    $child = $service->save($event, $owner, [
        'target' => 'team',
        'type' => 'text',
        'label' => 'Kontak PIC Bus',
        'depends_on_question_id' => $parent->id,
        'conditional_value' => 'Ya',
        'is_required' => true,
        'order' => 2,
    ]);

    $team = EventParticipant::factory()->create([
        'event_id' => $event->id,
        'user_id' => $owner->id,
        'community_id' => null,
    ]);

    $service->submit($event, $owner, 'team', $team->id, [
        (string) $parent->id => 'Tidak',
    ]);

    $saved = EventAnswer::where('event_id', $event->id)
        ->where('target', 'team')
        ->where('subject_id', $team->id)
        ->first();
    expect($saved->answers)->toHaveKey((string) $parent->id, 'Tidak')
        ->and($saved->answers)->not->toHaveKey((string) $child->id);

    expect(fn () => $service->submit($event, $owner, 'team', $team->id, [
        (string) $parent->id => 'Ya',
    ]))->toThrow(ValidationException::class);

    $service->submit($event, $owner, 'team', $team->id, [
        (string) $parent->id => 'Ya',
        (string) $child->id => '08123456789',
    ]);

    $updated = EventAnswer::where('event_id', $event->id)
        ->where('target', 'team')
        ->where('subject_id', $team->id)
        ->first();
    expect($updated->answers[(string) $parent->id])->toBe('Ya')
        ->and($updated->answers[(string) $child->id])->toBe('08123456789');
});

test('foreign target or unknown question submission is denied', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $service = app(EventQuestionService::class);

    $teamQuestion = $service->save($event, $owner, [
        'target' => 'team',
        'type' => 'text',
        'label' => 'Nama Manajer',
        'is_required' => true,
        'order' => 1,
    ]);

    $instQuestion = $service->save($event, $owner, [
        'target' => 'institution',
        'type' => 'text',
        'label' => 'Alamat Sekolah',
        'is_required' => false,
        'order' => 2,
    ]);

    $team = EventParticipant::factory()->create([
        'event_id' => $event->id,
        'user_id' => $owner->id,
        'community_id' => null,
    ]);

    expect(fn () => $service->submit($event, $owner, 'team', $team->id, [
        (string) $teamQuestion->id => 'Pak Rudi',
        (string) $instQuestion->id => 'Jl. Merdeka No 1',
    ]))->toThrow(ValidationException::class);

    expect(fn () => $service->submit($event, $owner, 'team', $team->id, [
        (string) $teamQuestion->id => 'Pak Rudi',
        '999999' => 'Hacker',
    ]))->toThrow(ValidationException::class);

    $stranger = User::factory()->create(['is_active' => true]);
    expect(fn () => $service->form($event, $stranger, 'team', $team->id))->toThrow(HttpException::class);
    expect(fn () => $service->submit($event, $stranger, 'team', $team->id, [
        (string) $teamQuestion->id => 'Pak Rudi',
    ]))->toThrow(HttpException::class);
});

test('participant target answers can only be accessed and submitted by participant self', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $service = app(EventQuestionService::class);

    $question = $service->save($event, $owner, [
        'target' => 'participant',
        'type' => 'text',
        'label' => 'Ukuran Jersey',
        'is_required' => true,
        'order' => 1,
    ]);

    $athlete = User::factory()->create(['is_active' => true]);
    $imposter = User::factory()->create(['is_active' => true]);

    $activity = EventActivity::create([
        'event_id' => $event->id,
        'name' => 'Lomba Basket 3x3',
        'capacity' => 20,
    ]);
    $registration = ActivityRegistration::create([
        'event_activity_id' => $activity->id,
        'user_id' => $athlete->id,
        'amount' => 50000,
        'status' => 'confirmed',
        'request_key' => (string) Str::uuid(),
    ]);
    $member = ActivityMember::create([
        'registration_id' => $registration->id,
        'user_id' => $athlete->id,
        'accepted_at' => now(),
    ]);

    expect(fn () => $service->form($event, $imposter, 'participant', $member->id))
        ->toThrow(HttpException::class);
    expect(fn () => $service->submit($event, $imposter, 'participant', $member->id, [(string) $question->id => 'L']))
        ->toThrow(HttpException::class);

    $form = $service->form($event, $athlete, 'participant', $member->id);
    expect($form['subject_summary']['name'])->toBe($athlete->name);

    $service->submit($event, $athlete, 'participant', $member->id, [
        (string) $question->id => 'XL',
    ]);

    $answer = EventAnswer::where('event_id', $event->id)
        ->where('target', 'participant')
        ->where('subject_id', $member->id)
        ->first();
    expect($answer->answers[(string) $question->id])->toBe('XL');
});

test('private PDF 2MB upload preserves existing file after invalid replacement attempt', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $service = app(EventQuestionService::class);

    $fileQuestion = $service->save($event, $owner, [
        'target' => 'team',
        'type' => 'file',
        'label' => 'Surat Rekomendasi Resmi (PDF maks 2MB)',
        'is_required' => true,
        'order' => 1,
    ]);

    $team = EventParticipant::factory()->create([
        'event_id' => $event->id,
        'user_id' => $owner->id,
        'community_id' => null,
    ]);

    $validPdf = UploadedFile::fake()->create('valid_surat.pdf', 1500, 'application/pdf');
    $service->submit($event, $owner, 'team', $team->id, [
        (string) $fileQuestion->id => $validPdf,
    ]);

    $answer = EventAnswer::where('event_id', $event->id)
        ->where('target', 'team')
        ->where('subject_id', $team->id)
        ->firstOrFail();

    $originalPath = $answer->answers[(string) $fileQuestion->id]['path'];
    expect(Storage::disk('local')->exists($originalPath))->toBeTrue();

    $oversizedFile = UploadedFile::fake()->create('huge_file.pdf', 3000, 'application/pdf');
    expect(fn () => $service->submit($event, $owner, 'team', $team->id, [
        (string) $fileQuestion->id => $oversizedFile,
    ]))->toThrow(ValidationException::class);

    expect(Storage::disk('local')->exists($originalPath))->toBeTrue();
    $answerAfterFailed = $answer->fresh();
    expect($answerAfterFailed->answers[(string) $fileQuestion->id]['path'])->toBe($originalPath);

    $invalidMimeFile = UploadedFile::fake()->create('malicious.exe', 500, 'application/x-msdownload');
    expect(fn () => $service->submit($event, $owner, 'team', $team->id, [
        (string) $fileQuestion->id => $invalidMimeFile,
    ]))->toThrow(ValidationException::class);

    expect(Storage::disk('local')->exists($originalPath))->toBeTrue();

    $service->submit($event, $owner, 'team', $team->id, [
        (string) $fileQuestion->id => null,
    ]);
    expect(Storage::disk('local')->exists($originalPath))->toBeTrue();
    expect($answer->fresh()->answers[(string) $fileQuestion->id]['path'])->toBe($originalPath);
});

test('school filter is authoritative via mocked CommunityLookup', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $service = app(EventQuestionService::class);

    $smaQuestion = $service->save($event, $owner, [
        'target' => 'institution',
        'type' => 'text',
        'label' => 'Kategori Khusus SMA',
        'school_levels' => ['SMA'],
        'order' => 1,
    ]);

    $smpQuestion = $service->save($event, $owner, [
        'target' => 'institution',
        'type' => 'text',
        'label' => 'Kategori Khusus SMP',
        'school_levels' => ['SMP'],
        'order' => 2,
    ]);

    $allQuestion = $service->save($event, $owner, [
        'target' => 'institution',
        'type' => 'text',
        'label' => 'Kategori Umum Semua Sekolah',
        'school_levels' => null,
        'order' => 3,
    ]);

    $mockLookup = Mockery::mock(CommunityLookup::class);
    $mockLookup->shouldReceive('canManage')->andReturn(true);

    $mockLookup->shouldReceive('find')->with(101)->andReturn(new CommunitySummary(
        id: 101,
        name: 'SMA Negeri 1',
        slug: 'sman-1',
        status: 'verified',
        subtype: 'SMA',
        sport_category_id: null,
        city_id: null,
        institution_type: 'school',
        is_active: true,
        school_level: 'SMA',
    ));

    $mockLookup->shouldReceive('find')->with(202)->andReturn(new CommunitySummary(
        id: 202,
        name: 'SMP Negeri 1',
        slug: 'smpn-1',
        status: 'verified',
        subtype: 'SMP',
        sport_category_id: null,
        city_id: null,
        institution_type: 'school',
        is_active: true,
        school_level: 'SMP',
    ));

    app()->instance(CommunityLookup::class, $mockLookup);

    $formSma = $service->form($event, $owner, 'institution', 101);
    $questionIdsSma = collect($formSma['questions'])->pluck('id')->all();
    expect($questionIdsSma)->toContain($smaQuestion->id)
        ->and($questionIdsSma)->toContain($allQuestion->id)
        ->and($questionIdsSma)->not->toContain($smpQuestion->id);

    $formSmp = $service->form($event, $owner, 'institution', 202);
    $questionIdsSmp = collect($formSmp['questions'])->pluck('id')->all();
    expect($questionIdsSmp)->toContain($smpQuestion->id)
        ->and($questionIdsSmp)->toContain($allQuestion->id)
        ->and($questionIdsSmp)->not->toContain($smaQuestion->id);
});

test('private answer downloads reject strangers and cross event targets', function (): void {
    Storage::fake('local');
    $owner = User::factory()->create(['is_active' => true]);
    $stranger = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $otherEvent = Event::factory()->create(['user_id' => $owner->id]);
    $team = EventParticipant::factory()->create(['event_id' => $event->id, 'user_id' => $owner->id, 'community_id' => null]);
    $service = app(EventQuestionService::class);
    $question = $service->save($event, $owner, ['target' => 'team', 'type' => 'file', 'label' => 'Berkas privat', 'is_required' => true]);
    $service->submit($event, $owner, 'team', $team->id, [$question->id => UploadedFile::fake()->image('demo.png')]);
    $answer = EventAnswer::where('event_id', $event->id)->firstOrFail();
    $role = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $owner->assignRole($role);
    $stranger->assignRole($role);
    $this->actingAs($owner)->get(route('panel.event-questions.download', [$event, $answer, $question->id]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('panel.event-questions.download', [$otherEvent, $answer, $question->id]))->assertNotFound();
    $this->actingAs($stranger)->get(route('panel.event-questions.download', [$event, $answer, $question->id]))->assertForbidden();
});

test('file MIME is checked from content even when filename is png', function (): void {
    Storage::fake('local');
    $owner = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $team = EventParticipant::factory()->create(['event_id' => $event->id, 'user_id' => $owner->id, 'community_id' => null]);
    $service = app(EventQuestionService::class);
    $question = $service->save($event, $owner, ['target' => 'team', 'type' => 'file', 'label' => 'Berkas privat', 'is_required' => true]);
    $path = Storage::disk('local')->path('spoof.png');
    Storage::disk('local')->put('spoof.png', '<script>alert("not an image")</script>');
    $file = new UploadedFile($path, 'spoof.png', 'image/png', null, true);
    expect(fn () => $service->submit($event, $owner, 'team', $team->id, [$question->id => $file]))->toThrow(ValidationException::class);
    expect(EventAnswer::where('event_id', $event->id)->exists())->toBeFalse();
});

test('school questions resolve actual lowercase master level including saved uppercase filters', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $npsn = (string) random_int(10000000, 99999999);
    DB::table('schools')->insert(['npsn' => $npsn, 'name' => 'DEMO SMA', 'school_level' => 'sma', 'school_status' => 'negeri']);
    $community = Community::factory()->create(['subtype' => 'school', 'npsn' => $npsn]);
    CommunityOfficial::factory()->create(['community_id' => $community->id, 'user_id' => $owner->id, 'status' => 'active', 'ended_at' => null]);
    $service = app(EventQuestionService::class);
    $question = $service->save($event, $owner, ['target' => 'institution', 'type' => 'text', 'label' => 'DEMO SMA only', 'school_levels' => ['SMA'], 'is_required' => true]);
    expect(app(CommunityLookup::class)->find($community->id)->school_level)->toBe('sma');
    expect(collect($service->form($event, $owner, 'institution', $community->id)['questions'])->pluck('id')->all())->toContain($question->id);
    expect(fn () => $service->submit($event, $owner, 'institution', $community->id, []))->toThrow(ValidationException::class);
});
