<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Event\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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

test('unverified user renders email verification warning with resend form and notice link, no nested form', function (): void {
    $user = new User([
        'id' => 1,
        'name' => 'Budi Santoso',
        'email' => 'budi.unverified@example.com',
        'email_verified_at' => null,
    ]);

    $this->actingAs($user);

    $event = new Event([
        'id' => 10,
        'slug' => 'turnamen-basket-2026',
        'title' => 'Turnamen Basket Nasional 2026',
        'status' => 'published',
        'registration_open' => true,
    ]);
    $event->setRelation('categories', new Collection([]));

    $community = new Community([
        'id' => 20,
        'name' => 'Klub Rajawali',
        'subtype' => 'club',
        'institution_type' => null,
        'school_level' => null,
    ]);

    $view = $this->view('panel.events.register', [
        'event' => $event,
        'community' => $community,
        'documentTypes' => collect(),
        'eligibleRosterByCategory' => [],
        'vaultDocuments' => collect(),
        'legalDocuments' => [],
        'registrations' => collect(),
        'registrationDrafts' => collect(),
        'maxTeamsPerEntity' => [],
    ]);

    $html = (string) $view;

    // Email and unverified status visible
    expect($html)
        ->toContain('budi.unverified@example.com')
        ->toContain('Belum Terverifikasi')
        ->toContain('Akses pendaftaran tetap terbuka')
        ->toContain(route('verification.notice'))
        ->toContain(route('verification.send'));

    // Resend button exists
    expect($html)->toContain('Kirim Ulang Verifikasi');

    // No nested forms: verification form must close before event store form opens
    $resendFormPos = strpos($html, route('verification.send'));
    expect($resendFormPos)->not->toBeFalse();

    $storeFormPos = strpos($html, route('panel.community-events.store', $event->slug));
    expect($storeFormPos)->not->toBeFalse();

    // The resend form occurs before the store form
    expect($resendFormPos)->toBeLessThan($storeFormPos);

    // Verify closing </form> between the resend form and store form
    $resendFormSubstr = substr($html, $resendFormPos, $storeFormPos - $resendFormPos);
    expect($resendFormSubstr)->toContain('</form>');

    // Strict no-nested-forms check: counting opening/closing form tags sequentially
    preg_match_all('/<form\b[^>]*>|<\/form>/i', $html, $matches);
    $formDepth = 0;
    $maxDepth = 0;
    foreach ($matches[0] as $tag) {
        if (str_starts_with(strtolower($tag), '<form')) {
            $formDepth++;
            if ($formDepth > $maxDepth) {
                $maxDepth = $formDepth;
            }
        } elseif (strtolower($tag) === '</form>') {
            $formDepth--;
        }
    }
    expect($maxDepth)->toBe(1);
    expect($formDepth)->toBe(0);
});

test('verified user renders email terverifikasi banner without resend form', function (): void {
    $user = new User([
        'id' => 2,
        'name' => 'Siti Verified',
        'email' => 'siti.verified@example.com',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user);

    $event = new Event([
        'id' => 10,
        'slug' => 'turnamen-basket-2026',
        'title' => 'Turnamen Basket Nasional 2026',
        'status' => 'published',
        'registration_open' => true,
    ]);
    $event->setRelation('categories', new Collection([]));

    $community = new Community([
        'id' => 20,
        'name' => 'Klub Rajawali',
        'subtype' => 'club',
        'institution_type' => null,
        'school_level' => null,
    ]);

    $view = $this->view('panel.events.register', [
        'event' => $event,
        'community' => $community,
        'documentTypes' => collect(),
        'eligibleRosterByCategory' => [],
        'vaultDocuments' => collect(),
        'legalDocuments' => [],
        'registrations' => collect(),
        'registrationDrafts' => collect(),
        'maxTeamsPerEntity' => [],
    ]);

    $html = (string) $view;

    // Verified banner
    expect($html)
        ->toContain('Email terverifikasi')
        ->toContain('siti.verified@example.com')
        ->toContain('Terverifikasi')
        ->not->toContain(route('verification.send'))
        ->not->toContain('Kirim Ulang Verifikasi');

    // Main registration form exists and no nested form
    preg_match_all('/<form\b[^>]*>|<\/form>/i', $html, $matches);
    $formDepth = 0;
    $maxDepth = 0;
    foreach ($matches[0] as $tag) {
        if (str_starts_with(strtolower($tag), '<form')) {
            $formDepth++;
            if ($formDepth > $maxDepth) {
                $maxDepth = $formDepth;
            }
        } elseif (strtolower($tag) === '</form>') {
            $formDepth--;
        }
    }
    expect($maxDepth)->toBe(1);
    expect($formDepth)->toBe(0);
});
