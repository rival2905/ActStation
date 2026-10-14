<?php

declare(strict_types=1);

use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
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

test('public registration view renders category-driven UI with top selector and no unconditional cards', function (): void {
    $event = new Event([
        'id' => 10,
        'slug' => 'championship-2026',
        'title' => 'Kejuaraan Nasional 2026',
        'status' => 'published',
        'registration_open' => true,
    ]);

    $categoryCommunity = new EventCategory([
        'id' => 101,
        'event_id' => 10,
        'name' => 'Kategori Antar Klub KU-16',
        'match_type' => '5x5',
        'gender' => 'male',
        'min_age' => 14,
        'max_age' => 16,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_fee' => 500000,
        'allow_club' => true,
        'allow_community' => false,
        'allow_individual' => false,
        'registration_flow' => 'screening_first',
    ]);

    $categoryIndividual = new EventCategory([
        'id' => 102,
        'event_id' => 10,
        'name' => 'Kontes 3-Point Perorangan',
        'match_type' => 'Kontes',
        'gender' => 'mixed',
        'min_age' => 16,
        'max_age' => 35,
        'min_roster' => 1,
        'max_roster' => 1,
        'registration_fee' => 100000,
        'allow_club' => false,
        'allow_community' => false,
        'allow_individual' => true,
        'registration_flow' => 'screening_first',
    ]);

    $event->setRelation('categories', new Collection([$categoryCommunity, $categoryIndividual]));

    $view = $this->view('public.events.register', [
        'event' => $event,
    ]);

    $html = (string) $view;

    // Unified top selector
    expect($html)
        ->toContain('id="event_category_id"')
        ->toContain('name="event_category_id"')
        ->toContain('value="101"')
        ->toContain('value="102"')
        ->toContain('Kategori Antar Klub KU-16')
        ->toContain('Kontes 3-Point Perorangan')
        // Panes exist per category
        ->toContain('id="category_pane_101"')
        ->toContain('id="category_pane_102"')
        // Community pane (101): has community CTA, no individual form
        ->toContain(route('panel.community-events.create', [$event->slug, 'event_category_id' => 101]))
        ->toContain('Daftar Tim pada Kategori Ini');

    // Pane 101 must not have individual form
    expect($html)->not->toContain('id="individual_form_101"');

    // Pane 102 must have individual form
    expect($html)
        ->toContain('id="individual_form_102"')
        ->toContain(route('events.register.individual', $event->slug))
        ->toContain('name="registration_source"')
        ->toContain('value="individual"')
        ->toContain('Kirim Pendaftaran Mandiri (Kontes 3-Point Perorangan)');
});

test('public registration view renders empty warning state when category allows neither community nor individual', function (): void {
    $event = new Event([
        'id' => 20,
        'slug' => 'invitation-tournament',
        'title' => 'Turnamen Tertutup',
        'status' => 'published',
        'registration_open' => true,
    ]);

    $categoryClosed = new EventCategory([
        'id' => 201,
        'event_id' => 20,
        'name' => 'Divisi Khusus Tertutup',
        'match_type' => '5x5',
        'gender' => 'male',
        'allow_club' => false,
        'allow_community' => false,
        'allow_individual' => false,
        'registration_flow' => 'by_invitation',
    ]);

    $event->setRelation('categories', new Collection([$categoryClosed]));

    $view = $this->view('public.events.register', [
        'event' => $event,
    ]);

    $html = (string) $view;

    expect($html)
        ->toContain('id="category_pane_201"')
        ->toContain('Jalur Pendaftaran Publik Belum Dibuka')
        ->toContain('Khusus Undangan Resmi (by invitation)')
        ->not->toContain('Daftar Tim pada Kategori Ini')
        ->not->toContain('id="individual_form_201"');
});

test('public registration view prevents individual form when category is by_invitation even if allow_individual is true', function (): void {
    $event = new Event([
        'id' => 30,
        'slug' => 'all-star-weekend',
        'title' => 'All Star Weekend',
        'status' => 'published',
        'registration_open' => true,
    ]);

    $categoryInvitationIndiv = new EventCategory([
        'id' => 301,
        'event_id' => 30,
        'name' => 'Slam Dunk Contest (Invitation Only)',
        'match_type' => 'Kontes',
        'gender' => 'male',
        'allow_club' => false,
        'allow_community' => false,
        'allow_individual' => true,
        'registration_flow' => 'by_invitation',
    ]);

    $event->setRelation('categories', new Collection([$categoryInvitationIndiv]));

    $view = $this->view('public.events.register', [
        'event' => $event,
    ]);

    $html = (string) $view;

    // Since registration_flow is by_invitation, individual public form must NOT be shown
    expect($html)
        ->toContain('id="category_pane_301"')
        ->toContain('Jalur Pendaftaran Publik Belum Dibuka')
        ->not->toContain('id="individual_form_301"');
});

test('category form fields render allow_individual switch with hidden 0 and three-criteria warning', function (): void {
    $event = new Event([
        'id' => 40,
        'title' => 'Piala Pelajar',
    ]);

    $view = $this->view('events.categories._form-fields', [
        'event' => $event,
        'category' => null,
        'fieldPrefix' => 'add_cat',
        'documentTypes' => collect(),
    ]);

    $html = (string) $view;

    expect($html)
        ->toContain('name="allow_individual"')
        ->toContain('id="add_cat_allow_individual"')
        ->toContain('Izinkan Atlet Mandiri / Perorangan')
        ->toContain('Minimal salah satu kriteria pendaftar (Klub, Komunitas, atau Atlet Mandiri) harus diaktifkan.');
});

test('category form fields disable allow_individual switch when participants exist and retain hidden input', function (): void {
    $event = new Event([
        'id' => 50,
        'title' => 'Liga Mahasiswa',
    ]);

    $categoryWithParticipants = new EventCategory([
        'id' => 501,
        'event_id' => 50,
        'name' => 'KU-21',
        'allow_club' => true,
        'allow_community' => false,
        'allow_individual' => true,
        'participants_count' => 8,
    ]);

    $view = $this->view('events.categories._form-fields', [
        'event' => $event,
        'category' => $categoryWithParticipants,
        'fieldPrefix' => 'edit_cat',
        'documentTypes' => collect(),
    ]);

    $html = (string) $view;

    // Checkbox should be disabled
    expect($html)
        ->toContain('id="edit_cat_allow_individual"')
        ->toContain('disabled')
        // Hidden input should carry the exact active boolean value '1'
        ->toContain('<input type="hidden" name="allow_individual" value="1">');
});
