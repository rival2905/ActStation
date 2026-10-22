<?php

declare(strict_types=1);

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolExtracurricular;
use App\Modules\Community\Models\SchoolExtracurricularAppointment;
use App\Modules\Community\Models\SchoolRegistration;
use App\Modules\Community\Models\SportsPersonnelRole;
use App\Modules\Community\Services\SchoolExtracurricularPersonnelService;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('school personnel notifications happen only after commit and transport failure keeps acceptance', function (): void {
    Http::preventStrayRequests();
    $delivery = new class implements EmailDelivery
    {
        public array $sent = [];

        public function send(array $message): int|string
        {
            $this->sent[] = $message;
            throw new RuntimeException('Test transport failure');
        }
    };
    app()->instance(EmailDelivery::class, $delivery);
    $manager = User::factory()->create(['is_active' => true]);
    $target = User::factory()->create(['is_active' => true]);
    $school = Community::factory()->create(['subtype' => 'school', 'is_active' => true, 'contact_email' => 'office@example.invalid']);
    $sport = SportCategory::create(['name' => 'Basket Test', 'slug' => 'basket-test', 'is_active' => true]);
    $unit = SchoolExtracurricular::create(['community_id' => $school->id, 'sport_category_id' => $sport->id]);
    CommunityOfficial::create(['community_id' => $school->id, 'user_id' => $manager->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    SchoolRegistration::create(['community_id' => $school->id, 'user_id' => $manager->id, 'npsn' => '12345678', 'contact_email' => $school->contact_email, 'email_hash' => hash('sha256', $school->contact_email), 'status' => 'approved', 'admin_review_status' => 'approved', 'email_verified_at' => now(), 'reviewed_at' => now()]);
    $role = SportsPersonnelRole::firstOrCreate(['code' => 'coach'], ['name' => 'Pelatih', 'is_active' => true, 'sort_order' => 1]);
    $service = app(SchoolExtracurricularPersonnelService::class);
    $params = ['target_user_id' => $target->id, 'sports_personnel_role_id' => $role->id, 'can_manage_personnel' => false];
    try {
        DB::transaction(function () use ($service, $unit, $manager, $params): void {
            $service->invite($unit, $manager, $params);
            throw new RuntimeException('Rollback fixture');
        });
    } catch (RuntimeException) {
    }
    expect($delivery->sent)->toHaveCount(0);
    expect(SchoolExtracurricularAppointment::count())->toBe(0);
    $invite = $service->invite($unit, $manager, $params);
    $before = count($delivery->sent);
    $service->accept($unit, $invite['appointment'], $target, $invite['token']);
    expect($invite['appointment']->fresh()->status)->toBe('active');
    expect(count($delivery->sent))->toBe($before + 1);
    $notice = $delivery->sent[array_key_last($delivery->sent)];
    expect($notice['recipient_email'])->toBe($school->contact_email)->and($notice['body'])->toContain('tidak memerlukan persetujuan');
});
