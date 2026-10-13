<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\EventParticipantMember;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\MasterData\Models\Venue;
use App\Modules\MasterData\Models\VenueCourt;
use App\Modules\Ticketing\Models\TicketCategory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ShowcaseEventSeeder extends Seeder
{
    public function run(): void
    {
        $basketball = SportCategory::where('slug', 'basketball')->first() ?? SportCategory::first();

        $venue = Venue::firstOrCreate(
            ['name' => 'GOR C-Tra Arena Bandung'],
            [
                'address' => 'Jl. Cikutra No. 201, Cibeunying Kidul, Bandung',
                'province_id' => '32',
                'city_id' => '3273',
                'capacity' => 2500,
                'is_active' => true,
            ]
        );

        VenueCourt::firstOrCreate(
            ['venue_id' => $venue->id, 'name' => 'Lapangan Utama (Court 1)'],
            [
                'surface' => 'parquet',
                'is_indoor' => true,
                'is_active' => true,
            ]
        );

        // Development levels
        $dlRemaja = DevelopmentLevel::firstOrCreate(
            ['name' => 'Remaja (13-15 Tahun)'],
            ['sport_category_id' => $basketball->id, 'min_age' => 13, 'max_age' => 15, 'sort_order' => 1, 'is_active' => true]
        );
        $dlTaruna = DevelopmentLevel::firstOrCreate(
            ['name' => 'Taruna (16-18 Tahun)'],
            ['sport_category_id' => $basketball->id, 'min_age' => 16, 'max_age' => 18, 'sort_order' => 2, 'is_active' => true]
        );
        $dlSenior = DevelopmentLevel::firstOrCreate(
            ['name' => 'Master / Veteran (35+ Tahun)'],
            ['sport_category_id' => $basketball->id, 'min_age' => 35, 'max_age' => 60, 'sort_order' => 3, 'is_active' => true]
        );

        // 1. Akun Organizer
        $organizer = User::firstOrCreate(
            ['email' => 'organizer@actstation.test'],
            [
                'name' => 'Bambang Panitia (EO Jabar)',
                'password' => Hash::make('password'),
                'phone_number' => '081234567890',
                'phone_normalized' => '+6281234567890',
                'identity_number' => '3273010101900001',
                'bank_name' => 'BCA',
                'bank_account_number' => '8880123456',
                'bank_account_holder' => 'Bambang Panitia',
                'emergency_contact' => '081299887766',
                'kyc_status' => 'verified',
                'is_active' => true,
            ]
        );
        $organizer->syncRoles(['organizer', 'member']);

        // 2. Akun Manager Komunitas
        $manager = User::firstOrCreate(
            ['email' => 'manager@actstation.test'],
            [
                'name' => 'Hendra Manajer (Dishub Jabar)',
                'password' => Hash::make('password'),
                'phone_number' => '081399887766',
                'phone_normalized' => '+6281399887766',
                'identity_number' => '3273010101850002',
                'is_active' => true,
            ]
        );
        $manager->syncRoles(['community_manager', 'member']);

        // 3. Komunitas Instansi & Sekolah
        $commInstansi = Community::firstOrCreate(
            ['slug' => 'dishub-provinsi-jabar'],
            [
                'name' => 'Dinas Perhubungan Provinsi Jawa Barat',
                'sport_category_id' => $basketball->id,
                'subtype' => 'institution',
                'status' => 'verified',
                'city_id' => '3273',
            ]
        );
        CommunityOfficial::firstOrCreate(
            ['community_id' => $commInstansi->id, 'user_id' => $manager->id],
            ['position' => 'Manajer Tim Basket', 'status' => 'active', 'started_at' => now()]
        );

        // Atlet Instansi dengan Digital Vault Dokumen
        for ($i = 1; $i <= 5; $i++) {
            $athUser = User::firstOrCreate(
                ['email' => "atlet_instansi_{$i}@actstation.test"],
                [
                    'name' => "Pemain Dishub {$i}",
                    'password' => Hash::make('password'),
                    'phone_number' => "08120000000{$i}",
                    'phone_normalized' => "+628120000000{$i}",
                    'identity_number' => "327399990000000{$i}",
                    'birth_date' => Carbon::now()->subYears(37)->format('Y-m-d'),
                    'gender' => 'male',
                    'is_active' => true,
                ]
            );
            $athUser->syncRoles(['athlete']);

            CommunityAthlete::firstOrCreate(
                ['community_id' => $commInstansi->id, 'user_id' => $athUser->id],
                [
                    'jersey_number' => $i * 10,
                    'position' => 'Guard',
                    'status' => 'active',
                    'region_status' => 'verified',
                    'joined_at' => now(),
                ]
            );

            // Seed Vault Dokumen untuk atlet 1 & 2 (BPJS & JMO sudah ada)
            if ($i <= 2) {
                Document::firstOrCreate(
                    [
                        'documentable_type' => User::class,
                        'documentable_id' => $athUser->id,
                        'category' => 'bpjs_tk',
                    ],
                    [
                        'name' => 'Kartu BPJS Ketenagakerjaan',
                        'file_path' => 'vault_documents/sample_bpjs.png',
                        'status' => 'verified',
                    ]
                );

                Document::firstOrCreate(
                    [
                        'documentable_type' => User::class,
                        'documentable_id' => $athUser->id,
                        'category' => 'jmo_screenshot',
                    ],
                    [
                        'name' => 'Screenshot Profil JMO Aktif Bekerja',
                        'file_path' => 'vault_documents/sample_jmo.png',
                        'status' => 'verified',
                    ]
                );
            }
        }

        // 4. Showcase Event: Piala Kemerdekaan Basket Jabar 2026
        $event = Event::firstOrCreate(
            ['slug' => 'piala-kemerdekaan-basket-jabar-2026'],
            [
                'sport_category_id' => $basketball->id,
                'venue_id' => $venue->id,
                'user_id' => $organizer->id,
                'title' => 'Piala Kemerdekaan Basket Jabar 2026',
                'description' => "Kejuaraan Bola Basket Terbuka Jawa Barat 2026 memperebutkan Piala Bergilir Kemerdekaan.\n\nMempertandingkan kategori Instansi / Kedinasan KU-35++ serta Pelajar SMP dan SMA Putra/Putri.\n\nSemua transaksi pendaftaran dan tiket penonton bergaransi 100% Escrow Platform ActStation.",
                'status' => 'published',
                'registration_open' => true,
                'max_participants' => 40,
                'starts_at' => Carbon::now()->addDays(5)->setHour(8)->setMinute(0),
                'ends_at' => Carbon::now()->addDays(10)->setHour(21)->setMinute(0),
                'registration_deadline' => Carbon::now()->addDays(3)->setHour(23)->setMinute(59),
                'allow_manual_payment' => true,
                'manual_bank_name' => 'BCA',
                'manual_bank_account_number' => '8880123456',
                'manual_bank_account_holder' => 'Bambang Panitia (EO Jabar)',
                'manual_payment_instructions' => 'Sertakan berita transfer: NAMA_TIM_KATEGORI. Simpan struk transfer untuk diunggah pada konfirmasi pembayaran.',
                'payout_status' => 'none',
            ]
        );

        // 5 Kategori
        $catInstansi = EventCategory::firstOrCreate(
            ['event_id' => $event->id, 'slug' => 'instansi-dinas-putra-ku-35'],
            [
                'name' => 'Instansi / Dinas Putra (KU 35++)',
                'match_type' => '5x5',
                'gender' => 'male',
                'development_level_id' => $dlSenior->id,
                'min_age' => 35,
                'registration_fee' => 1000000.00,
                'min_roster' => 5,
                'max_roster' => 12,
                'max_teams' => 8,
                'registration_flow' => 'screening_first',
                'enable_waiting_list' => true,
                'payment_time_limit_hours' => 24,
                'required_documents' => ['bpjs_tk', 'jmo_screenshot', 'employee_card', 'id_card', 'recommendation_letter'],
            ]
        );

        EventCategory::firstOrCreate(
            ['event_id' => $event->id, 'slug' => 'smp-putra'],
            [
                'name' => 'Pelajar SMP Putra',
                'match_type' => '5x5',
                'gender' => 'male',
                'development_level_id' => $dlRemaja->id,
                'min_age' => 13,
                'max_age' => 15,
                'registration_fee' => 1000000.00,
                'min_roster' => 5,
                'max_roster' => 12,
                'max_teams' => 8,
                'registration_flow' => 'screening_first',
                'enable_waiting_list' => true,
                'payment_time_limit_hours' => 24,
                'required_documents' => ['student_card', 'report_card', 'birth_certificate', 'family_card', 'photo', 'recommendation_letter'],
            ]
        );

        EventCategory::firstOrCreate(
            ['event_id' => $event->id, 'slug' => 'smp-putri'],
            [
                'name' => 'Pelajar SMP Putri',
                'match_type' => '5x5',
                'gender' => 'female',
                'development_level_id' => $dlRemaja->id,
                'min_age' => 13,
                'max_age' => 15,
                'registration_fee' => 1000000.00,
                'min_roster' => 5,
                'max_roster' => 12,
                'max_teams' => 8,
                'registration_flow' => 'screening_first',
                'enable_waiting_list' => true,
                'payment_time_limit_hours' => 24,
                'required_documents' => ['student_card', 'report_card', 'birth_certificate', 'family_card', 'photo', 'recommendation_letter'],
            ]
        );

        EventCategory::firstOrCreate(
            ['event_id' => $event->id, 'slug' => 'sma-putra'],
            [
                'name' => 'Pelajar SMA Putra',
                'match_type' => '5x5',
                'gender' => 'male',
                'development_level_id' => $dlTaruna->id,
                'min_age' => 16,
                'max_age' => 18,
                'registration_fee' => 1000000.00,
                'min_roster' => 5,
                'max_roster' => 12,
                'max_teams' => 8,
                'registration_flow' => 'screening_first',
                'enable_waiting_list' => true,
                'payment_time_limit_hours' => 24,
                'required_documents' => ['student_card', 'report_card', 'birth_certificate', 'family_card', 'photo', 'recommendation_letter'],
            ]
        );

        EventCategory::firstOrCreate(
            ['event_id' => $event->id, 'slug' => 'sma-putri'],
            [
                'name' => 'Pelajar SMA Putri',
                'match_type' => '5x5',
                'gender' => 'female',
                'development_level_id' => $dlTaruna->id,
                'min_age' => 16,
                'max_age' => 18,
                'registration_fee' => 1000000.00,
                'min_roster' => 5,
                'max_roster' => 12,
                'max_teams' => 8,
                'registration_flow' => 'screening_first',
                'enable_waiting_list' => true,
                'payment_time_limit_hours' => 24,
                'required_documents' => ['student_card', 'report_card', 'birth_certificate', 'family_card', 'photo', 'recommendation_letter'],
            ]
        );

        // Kategori Tiket
        TicketCategory::firstOrCreate(
            ['event_id' => $event->id, 'name' => 'Tribun Reguler Suporter'],
            [
                'price' => 25000.00,
                'quota' => 300,
                'sold_count' => 85,
                'access_type' => 'fullday',
                'seating_tier' => 'standard',
                'seating_zone' => 'Tribun Timur',
                'is_active' => true,
            ]
        );

        TicketCategory::firstOrCreate(
            ['event_id' => $event->id, 'name' => 'VIP Courtside Baris Depan'],
            [
                'price' => 100000.00,
                'quota' => 30,
                'sold_count' => 18,
                'access_type' => 'fullday',
                'seating_tier' => 'courtside',
                'seating_zone' => 'Courtside VIP',
                'is_active' => true,
            ]
        );

        // Tim pendaftar showcase: Dishub Jabar di Kategori Instansi
        $part = EventParticipant::firstOrCreate(
            ['event_id' => $event->id, 'team_name' => 'Dishub Jabar All-Stars'],
            [
                'event_category_id' => $catInstansi->id,
                'community_id' => $commInstansi->id,
                'user_id' => $manager->id,
                'registrant_type' => 'community',
                'contact_person' => 'Hendra Manajer',
                'contact_phone' => '081399887766',
                'status' => 'registered',
                'registered_at' => now(),
                'payment_channel' => 'gateway_escrow',
            ]
        );

        for ($i = 1; $i <= 5; $i++) {
            $athUser = User::where('email', "atlet_instansi_{$i}@actstation.test")->first();
            if ($athUser) {
                EventParticipantMember::firstOrCreate(
                    ['event_participant_id' => $part->id, 'user_id' => $athUser->id],
                    [
                        'jersey_number' => $i * 10,
                        'is_captain' => ($i === 1),
                        'position' => ($i === 1 ? 'PG' : ($i === 2 ? 'SG' : ($i === 3 ? 'SF' : ($i === 4 ? 'PF' : 'C')))),
                        'team_role' => 'player',
                    ]
                );
            }
        }
    }
}
