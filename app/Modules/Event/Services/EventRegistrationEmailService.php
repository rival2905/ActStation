<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventRegistrationEmailService
{
    /**
     * Build an immutable snapshot of event participant data at transition moment.
     *
     * @return array{
     *     transition: string,
     *     participant_id: int,
     *     user_id: int,
     *     recipient_email: string,
     *     recipient_name: string,
     *     team_name: string,
     *     status: string,
     *     revision_notes: string,
     *     event_title: string,
     *     event_slug: string,
     *     category_name: string,
     *     fee: string,
     *     deadline: string|null,
     *     invoice_number: string|null
     * }
     */
    public function createSnapshot(EventParticipant $participant, string $transition): array
    {
        $user = $participant->relationLoaded('user')
            ? $participant->user
            : ($participant->user_id ? User::query()->find($participant->user_id) : null);

        $event = $participant->relationLoaded('event')
            ? $participant->event
            : ($participant->event_id ? Event::query()->find($participant->event_id) : null);

        $category = $participant->relationLoaded('category')
            ? $participant->category
            : ($participant->event_category_id ? EventCategory::query()->find($participant->event_category_id) : null);

        $deadline = $participant->payment_deadline_at?->format('d M Y H:i');
        $fee = (string) ($category?->registration_fee ?? '0.00');
        $invoiceNumber = null;

        $trx = DB::table('transactions')
            ->where('payable_type', 'event_participant')
            ->where('payable_id', $participant->id)
            ->whereIn('status', ['pending', 'paid'])
            ->orderByDesc('id')
            ->first();

        if ($trx) {
            $invoiceNumber = $trx->invoice_number;
            if ($trx->expired_at && empty($deadline)) {
                $deadline = Carbon::parse($trx->expired_at)->format('d M Y H:i');
            }
            if ($trx->total !== null) {
                $fee = (string) $trx->total;
            } elseif ($trx->amount !== null) {
                $fee = (string) $trx->amount;
            }
        }

        return [
            'transition' => $transition,
            'participant_id' => (int) $participant->id,
            'user_id' => (int) ($participant->user_id ?? 0),
            'recipient_email' => (string) ($user?->email ?? ''),
            'recipient_name' => (string) ($user?->name ?? 'Pendaftar'),
            'team_name' => (string) $participant->team_name,
            'status' => (string) $participant->status,
            'revision_notes' => (string) ($participant->revision_notes ?? ''),
            'event_title' => (string) ($event?->title ?? 'Turnamen'),
            'event_slug' => (string) ($event?->slug ?? ''),
            'category_name' => (string) ($category?->name ?? 'Kategori'),
            'fee' => $fee,
            'deadline' => $deadline,
            'invoice_number' => $invoiceNumber,
        ];
    }

    /**
     * Send email notification based on immutable transition snapshot.
     */
    public function sendNotification(array $snapshot): int|string|null
    {
        try {
            if (! app()->bound(EmailDelivery::class)) {
                return null;
            }

            if (empty($snapshot['recipient_email'])) {
                return null;
            }

            // If invoice number or deadline was not set at snapshot time (e.g. transaction created after update in same DB transaction)
            if (empty($snapshot['invoice_number'])) {
                $trx = DB::table('transactions')
                    ->where('payable_type', 'event_participant')
                    ->where('payable_id', $snapshot['participant_id'])
                    ->orderByDesc('id')
                    ->first();

                if ($trx) {
                    $snapshot['invoice_number'] = $trx->invoice_number;
                    if (empty($snapshot['deadline']) && $trx->expired_at) {
                        $snapshot['deadline'] = Carbon::parse($trx->expired_at)->format('d M Y H:i');
                    }
                    if ($trx->total !== null) {
                        $snapshot['fee'] = (string) $trx->total;
                    }
                }
            }

            $payload = $this->buildPayload($snapshot);

            /** @var EmailDelivery $delivery */
            $delivery = app(EmailDelivery::class);

            return $delivery->send($payload);
        } catch (\Throwable $e) {
            Log::warning('Event registration lifecycle email delivery failed', [
                'type' => 'event_registration',
                'participant_id' => $snapshot['participant_id'] ?? null,
                'transition' => $snapshot['transition'] ?? 'unknown',
                'error_type' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * Format email payload.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *     recipient_user_id: int,
     *     recipient_email: string,
     *     recipient_name: string,
     *     name: string,
     *     subject: string,
     *     body: string,
     *     action_url: string,
     *     action_label: string,
     *     type: string,
     *     attachments: array<empty>
     * }
     */
    public function buildPayload(array $snapshot): array
    {
        $team = (string) ($snapshot['team_name'] ?? '');
        $event = (string) ($snapshot['event_title'] ?? '');
        $category = (string) ($snapshot['category_name'] ?? '');
        $status = (string) ($snapshot['status'] ?? '');
        $transition = (string) ($snapshot['transition'] ?? '');
        $name = (string) ($snapshot['recipient_name'] ?? '');

        $formattedFee = 'Rp '.number_format((float) ($snapshot['fee'] ?? 0), 0, ',', '.');
        $deadline = (string) ($snapshot['deadline'] ?? '-');
        $invoice = (string) ($snapshot['invoice_number'] ?? '-');

        $body = "Halo {$name},\n\n";

        switch ($transition) {
            case 'payment_verified':
                $subject = "Pembayaran Terverifikasi: {$event} - {$team}";
                $body .= "Pembayaran pendaftaran tim [{$team}] untuk event {$event} (Kategori: {$category}) telah diverifikasi lunas.\n";
                $body .= "Status: Terverifikasi (payment_verified)\n\n";
                $body .= 'Langkah selanjutnya: Pantau jadwal dan persiapan tim Anda melalui portal turnamen.';
                $actionUrl = route('my-events.index');
                $actionLabel = 'Lihat Pendaftaran';
                break;

            case 'pending_payment':
                $subject = "Tagihan Pembayaran Pendaftaran: {$event} - {$team}";
                $body .= "Berkas pendaftaran tim [{$team}] untuk event {$event} (Kategori: {$category}) telah lolos kurasi/verifikasi panitia.\n";
                $body .= "Status: Menunggu Pembayaran (pending_payment)\n";
                $body .= "Nomor Tagihan: {$invoice}\n";
                $body .= "Biaya Pendaftaran: {$formattedFee}\n";
                $body .= "Batas Waktu Pembayaran: {$deadline}\n\n";
                $body .= 'Langkah selanjutnya: Silakan selesaikan pembayaran pendaftaran sebelum batas waktu berakhir.';
                $actionUrl = route('panel.community-events.checkout', $snapshot['participant_id']);
                $actionLabel = 'Bayar Pendaftaran';
                break;

            case 'revision_needed':
                $subject = "Perbaikan Berkas Pendaftaran: {$event} - {$team}";
                $notes = (! empty($snapshot['revision_notes'])) ? $snapshot['revision_notes'] : 'Mohon periksa dan lengkapi berkas pendaftaran Anda.';
                $body .= "Pendaftaran tim [{$team}] untuk event {$event} (Kategori: {$category}) memerlukan perbaikan berkas.\n";
                $body .= "Status: Perlu Revisi (revision_needed)\n";
                $body .= "Catatan Panitia: {$notes}\n\n";
                $body .= 'Langkah selanjutnya: Silakan perbarui data atau berkas tim Anda melalui panel pendaftaran.';
                $actionUrl = route('my-events.index');
                $actionLabel = 'Lihat Pendaftaran';
                break;

            case 'confirmed':
                $subject = "Pendaftaran Terkonfirmasi: {$event} - {$team}";
                $body .= "Pendaftaran tim [{$team}] untuk event {$event} (Kategori: {$category}) telah resmi terkonfirmasi.\n";
                $body .= "Status: Terkonfirmasi (confirmed)\n\n";
                $body .= 'Langkah selanjutnya: Roster tim telah resmi dikunci (Roster Lock). Pantau jadwal pertandingan pada portal turnamen.';
                $actionUrl = route('my-events.index');
                $actionLabel = 'Lihat Pendaftaran';
                break;

            case 'cancelled':
            case 'rejected':
                $subject = "Status Pendaftaran: {$event} - {$team}";
                $body .= "Pendaftaran tim [{$team}] untuk event {$event} (Kategori: {$category}) saat ini berstatus {$status}.\n";
                $body .= 'Status: '.ucfirst($status)."\n\n";
                $body .= 'Langkah selanjutnya: Silakan hubungi panitia penyelenggara jika Anda memiliki pertanyaan.';
                $actionUrl = route('my-events.index');
                $actionLabel = 'Lihat Pendaftaran';
                break;

            case 'waiting_list':
                $subject = "Pendaftaran Waiting List: {$event} - {$team}";
                $body .= "Pendaftaran tim [{$team}] untuk event {$event} (Kategori: {$category}) masuk dalam daftar tunggu (waiting list).\n";
                $body .= "Status: Waiting List (waiting_list)\n\n";
                $body .= 'Langkah selanjutnya: Tim Anda akan dipromosikan secara otomatis jika kuota tim utama terbuka.';
                $actionUrl = route('my-events.index');
                $actionLabel = 'Lihat Pendaftaran';
                break;

            case 'deleted':
                $subject = "Pembatalan Pendaftaran: {$event} - {$team}";
                $body .= "Pendaftaran tim [{$team}] untuk event {$event} (Kategori: {$category}) telah dibatalkan / dihapus dari daftar peserta.\n";
                $body .= "Status: Dibatalkan / Dihapus\n\n";
                $body .= 'Langkah selanjutnya: Anda dapat melihat informasi turnamen lainnya melalui portal publik.';
                $actionUrl = ! empty($snapshot['event_slug']) ? route('events.show', $snapshot['event_slug']) : url('/');
                $actionLabel = 'Lihat Event';
                break;

            case 'created':
            default:
                if ($status === 'pending_payment') {
                    $subject = "Tagihan Pembayaran Pendaftaran: {$event} - {$team}";
                    $body .= "Pendaftaran tim [{$team}] untuk event {$event} (Kategori: {$category}) berhasil dibuat dan menunggu pembayaran.\n";
                    $body .= "Status: Menunggu Pembayaran (pending_payment)\n";
                    $body .= "Nomor Tagihan: {$invoice}\n";
                    $body .= "Biaya Pendaftaran: {$formattedFee}\n";
                    $body .= "Batas Waktu Pembayaran: {$deadline}\n\n";
                    $body .= 'Langkah selanjutnya: Silakan selesaikan pembayaran pendaftaran sebelum batas waktu berakhir.';
                    $actionUrl = route('panel.community-events.checkout', $snapshot['participant_id']);
                    $actionLabel = 'Bayar Pendaftaran';
                } elseif ($status === 'waiting_list') {
                    $subject = "Pendaftaran Waiting List: {$event} - {$team}";
                    $body .= "Pendaftaran tim [{$team}] untuk event {$event} (Kategori: {$category}) masuk dalam daftar tunggu (waiting list).\n";
                    $body .= "Status: Waiting List (waiting_list)\n\n";
                    $body .= 'Langkah selanjutnya: Tim Anda akan dipromosikan jika kuota peserta turnamen tersedia.';
                    $actionUrl = route('my-events.index');
                    $actionLabel = 'Lihat Pendaftaran';
                } else {
                    $subject = "Pendaftaran Berhasil: {$event} - {$team}";
                    $body .= "Pendaftaran tim [{$team}] untuk event {$event} (Kategori: {$category}) telah berhasil dibuat.\n";
                    $body .= "Status: Terdaftar (registered)\n\n";
                    $body .= 'Langkah selanjutnya: Menunggu proses kurasi dan verifikasi berkas oleh panitia turnamen.';
                    $actionUrl = route('my-events.index');
                    $actionLabel = 'Lihat Pendaftaran';
                }
                break;
        }

        return [
            'recipient_user_id' => (int) $snapshot['user_id'],
            'recipient_email' => (string) $snapshot['recipient_email'],
            'recipient_name' => (string) $snapshot['recipient_name'],
            'name' => (string) $snapshot['recipient_name'],
            'subject' => $subject,
            'body' => $body,
            'action_url' => $actionUrl,
            'action_label' => $actionLabel,
            'type' => 'information',
            'attachments' => [],
        ];
    }
}
