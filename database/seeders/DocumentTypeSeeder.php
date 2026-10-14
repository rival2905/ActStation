<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'code' => 'birth_certificate',
                'name' => 'Akta Kelahiran',
                'scope' => 'athlete',
                'applicable_entities' => ['club', 'school', 'free_association'],
                'description' => 'Bukti sah tanggal lahir untuk verifikasi kelompok umur (KU) dan pencegahan manipulasi usia.',
                'sort_order' => 1,
            ],
            [
                'code' => 'family_card',
                'name' => 'Kartu Keluarga (KK)',
                'scope' => 'athlete',
                'applicable_entities' => ['club', 'school', 'free_association'],
                'description' => 'Dokumen verifikasi NIK, domisili, dan relasi orang tua/wali atlet binaan.',
                'sort_order' => 2,
            ],
            [
                'code' => 'id_card',
                'name' => 'KTP / Kartu Identitas Anak (KIA)',
                'scope' => 'athlete',
                'applicable_entities' => ['club', 'school', 'university', 'institution', 'free_association'],
                'description' => 'Kartu tanda penduduk resmi atlet untuk pencocokan identitas fisik.',
                'sort_order' => 3,
            ],
            [
                'code' => 'photo',
                'name' => 'Pasfoto Formal Atlet',
                'scope' => 'athlete',
                'applicable_entities' => ['club', 'school', 'university', 'institution', 'free_association'],
                'description' => 'Foto wajah close-up terbaru untuk kartu ID atlet dan verifikasi pemindai digital di gate GOR.',
                'sort_order' => 4,
            ],
            [
                'code' => 'student_card',
                'name' => 'Kartu Pelajar',
                'scope' => 'athlete',
                'applicable_entities' => ['school'],
                'description' => 'Bukti identitas siswa terdaftar di sekolah yang bersangkutan.',
                'sort_order' => 5,
            ],
            [
                'code' => 'report_card',
                'name' => 'Halaman Biodata Raport Aktif / NISN',
                'scope' => 'athlete',
                'applicable_entities' => ['school'],
                'description' => 'Salinan raport halaman data diri yang mencantumkan NISN dan status aktif sekolah.',
                'sort_order' => 6,
            ],
            [
                'code' => 'student_id_card',
                'name' => 'KTM (Kartu Tanda Mahasiswa)',
                'scope' => 'athlete',
                'applicable_entities' => ['university'],
                'description' => 'Kartu tanda mahasiswa resmi perguruan tinggi bersangkutan.',
                'sort_order' => 7,
            ],
            [
                'code' => 'krs_active',
                'name' => 'KRS Semester Berjalan / Surat Keterangan Aktif',
                'scope' => 'athlete',
                'applicable_entities' => ['university'],
                'description' => 'Bukti bahwa mahasiswa aktif menempuh perkuliahan pada semester berjalan.',
                'sort_order' => 8,
            ],
            [
                'code' => 'employee_card',
                'name' => 'ID Card Pegawai / Kartu Karyawan',
                'scope' => 'athlete',
                'applicable_entities' => ['institution'],
                'description' => 'Tanda pengenal resmi pegawai instansi, korporasi, atau BUMN terkait.',
                'sort_order' => 9,
            ],
            [
                'code' => 'bpjs_tk',
                'name' => 'Kartu BPJS Ketenagakerjaan',
                'scope' => 'athlete',
                'applicable_entities' => ['institution'],
                'description' => 'Bukti kepesertaan jaminan ketenagakerjaan resmi sebagai karyawan aktif.',
                'sort_order' => 10,
            ],
            [
                'code' => 'jmo_screenshot',
                'name' => 'Tangkapan Layar (Screenshot) JMO Aktif Bekerja',
                'scope' => 'athlete',
                'applicable_entities' => ['institution'],
                'description' => 'Screenshot halaman profil aplikasi Jamsostek Mobile (JMO) yang menunjukkan status kepesertaan "Aktif Bekerja".',
                'sort_order' => 11,
            ],
            [
                'code' => 'recommendation_letter',
                'name' => 'Surat Tugas / Rekomendasi / SPTJM Tim',
                'scope' => 'team',
                'applicable_entities' => ['club', 'school', 'university', 'institution', 'free_association'],
                'description' => 'Surat izin resmi kepala sekolah, rektorat, pimpinan instansi, atau pengurus klub.',
                'sort_order' => 12,
            ],
            [
                'code' => 'school_team_letter',
                'name' => 'Surat Keterangan Sekolah untuk Tim',
                'scope' => 'team',
                'applicable_entities' => ['school'],
                'description' => 'Satu surat sekolah yang menerangkan atau mengizinkan seluruh roster tim, bukan berkas pribadi per atlet.',
                'sort_order' => 13,
            ],
            [
                'code' => 'team_assignment_letter',
                'name' => 'Surat Penugasan Tim',
                'scope' => 'team',
                'applicable_entities' => ['club', 'school', 'university', 'institution', 'free_association'],
                'description' => 'Surat penugasan resmi untuk satu tim dari sekolah, kampus, instansi, atau pengurus.',
                'sort_order' => 14,
            ],
            [
                'code' => 'team_mandate_letter',
                'name' => 'Surat Mandat / Perwakilan Tim',
                'scope' => 'team',
                'applicable_entities' => ['club', 'school', 'university', 'institution', 'free_association'],
                'description' => 'Mandat mewakili entitas pada pendaftaran tim ini. Diunggah sekali per tim oleh PIC.',
                'sort_order' => 15,
            ],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
