<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCommitteeMember;

class EventCommitteeAccess
{
    public const CAPABILITIES = [
        'teams.view' => ['label' => 'Lihat Tim Peserta', 'description' => 'Melihat pendaftaran, roster dan dokumen persyaratan privat peserta event ini.'],
        'teams.review' => ['label' => 'Periksa Tim & Dokumen', 'description' => 'Memutuskan pemeriksaan berkas, menyetujui tim dan meminta perbaikan. Tidak dapat menghapus peserta.'],
        'payments.confirm' => ['label' => 'Konfirmasi Pembayaran', 'description' => 'Memeriksa dan mengonfirmasi bukti pembayaran manual peserta. Tidak dapat mengubah rekening atau mencairkan dana.'],
        'documents.manage' => ['label' => 'Kelola Dokumen Event', 'description' => 'Mengunggah, menghapus dan mengatur publikasi dokumen event. Bukan verifikasi legalitas oleh admin.'],
        'schedule.manage' => ['label' => 'Kelola Jadwal & Bagan', 'description' => 'Mengelola bagan, pool, jadwal, lapangan, perangkat pertandingan serta skor/finalisasi di bagan. Akses sensitif: dapat mereset kompetisi.'],
        'results.manage' => ['label' => 'Kelola Hasil Pertandingan', 'description' => 'Mengisi skor pertandingan. Finalisasi melalui bagan memerlukan akses Jadwal & Bagan.'],
    ];

    public const PRESETS = [
        'ketua' => ['name' => 'Ketua', 'description' => 'Mengoordinasikan panitia dan memantau peserta.', 'capabilities' => ['teams.view']],
        'sekretaris' => ['name' => 'Sekretaris', 'description' => 'Mengelola administrasi dan dokumen event.', 'capabilities' => ['teams.view', 'documents.manage']],
        'bendahara' => ['name' => 'Bendahara', 'description' => 'Memeriksa pembayaran peserta, tanpa akses pencairan atau rekening.', 'capabilities' => ['teams.view', 'payments.confirm']],
        'verifikator' => ['name' => 'Verifikator', 'description' => 'Memeriksa kelengkapan dan persyaratan peserta.', 'capabilities' => ['teams.view', 'teams.review']],
        'pertandingan' => ['name' => 'Bidang Pertandingan', 'description' => 'Mengelola jadwal, bagan dan hasil pertandingan.', 'capabilities' => ['schedule.manage', 'results.manage']],
        'dokumentasi' => ['name' => 'Dokumentasi', 'description' => 'Mendokumentasikan kegiatan di lapangan. Bawaan tanpa akses data peserta atau pengelolaan event.', 'capabilities' => []],
    ];

    public function owner(Event $event, ?User $user): bool
    {
        if ($user && ! array_key_exists('is_active', $user->getAttributes())) {
            $user->refresh();
        }

        return $user && $user->is_active && ((int) $event->user_id === (int) $user->id || $user->hasRole('superadmin'));
    }

    public function allows(Event $event, ?User $user, ?string $capability): bool
    {
        if ($this->owner($event, $user)) {
            return true;
        }
        if (! $user?->is_active || ! $capability || ! isset(self::CAPABILITIES[$capability])) {
            return false;
        }

        return EventCommitteeMember::query()
            ->where('event_id', $event->id)->where('user_id', $user->id)->where('status', 'approved')
            ->whereHas('role', fn ($q) => $q->where('event_id', $event->id)->where('is_active', true)->whereJsonContains('capabilities', $capability))->exists();
    }

    public function authorize(Event $event, ?string $capability = null): void
    {
        abort_unless($this->allows($event, request()->user(), $capability), 403, 'Jabatan panitia Anda tidak memiliki akses untuk tindakan ini.');
    }

    public function current(Event $event): void
    {
        $action = request()->route()?->getActionMethod();
        $controller = class_basename(request()->route()?->getControllerClass() ?? '');
        $capability = match ($controller) {
            'OrganizerTeamController' => match ($action) {
                'index', 'downloadDocument' => 'teams.view',
                'verify', 'requestRevision' => 'teams.review',
                'confirmManualPayment' => 'payments.confirm',
                default => null,
            },
            'OrganizerDocumentController' => 'documents.manage',
            'OrganizerScheduleController' => 'schedule.manage',
            'OrganizerResultController' => 'results.manage',
            default => null,
        };
        $this->authorize($event, $capability);
    }
}
