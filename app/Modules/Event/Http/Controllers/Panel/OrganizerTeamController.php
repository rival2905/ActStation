<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Exceptions\RosterValidationException;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\RosterValidator;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrganizerTeamController extends Controller
{
    public function __construct(private readonly RosterValidator $rosterValidator) {}

    public function index(Request $request, Event $event): View
    {
        $this->authorizeOrganizer($event);

        $query = EventParticipant::query()
            ->where('event_id', $event->id)
            ->with(['category', 'members.user', 'paymentVerifiedBy']);

        if ($request->filled('category_id')) {
            $query->where('event_category_id', $request->input('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $participants = $query->latest('registered_at')->paginate(20)->withQueryString();
        $categories = $event->categories;
        $workspace = $request->routeIs('admin.*') ? 'admin' : 'organizer';

        $paidParticipantIds = DB::table('transactions')
            ->where('payable_type', 'event_participant')
            ->where('status', 'paid')
            ->whereIn('payable_id', $participants->pluck('id'))
            ->pluck('payable_id')
            ->all();

        return view('panel.organizer.teams.index', compact('event', 'participants', 'categories', 'workspace', 'paidParticipantIds'));
    }

    public function verify(Event $event, EventParticipant $participant): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        $this->authorizeParticipant($event, $participant);
        $participant->loadMissing(['category', 'members']);

        $category = $participant->category;
        abort_unless($category, 422, 'Kategori peserta tidak ditemukan.');

        if (! in_array($participant->status, ['registered', 'revision_needed', 'verified'], true)) {
            return back()->with('error', 'Status pendaftaran ini tidak dapat diverifikasi.');
        }
        $hasFee = (float) $category->registration_fee > 0;
        $flow = (string) $category->registration_flow;

        if ($participant->members->isEmpty()) {
            return back()->with('error', 'Roster peserta belum tersedia dan tidak dapat diverifikasi.');
        }

        try {
            $this->rosterValidator->validate($participant, $participant->members->map(fn ($member): array => [
                'user_id' => (int) $member->user_id,
                'jersey_number' => $member->jersey_number,
                'is_captain' => (bool) $member->is_captain,
                'position' => $member->position,
                'team_role' => $member->team_role,
            ])->all());
        } catch (RosterValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', 'Roster tidak lolos validasi kategori.');
        }

        $requiredDocuments = $category->required_documents ?? [];
        if (! empty($requiredDocuments)) {
            $documentTypes = DocumentType::query()->whereIn('code', $requiredDocuments)->get()->keyBy('code');
            $memberIds = $participant->members->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
            $athleteDocuments = Document::query()
                ->where('documentable_type', User::class)
                ->whereIn('documentable_id', $memberIds)
                ->whereIn('category', $requiredDocuments)
                ->where('status', 'verified')
                ->get();
            $teamDocuments = Document::query()
                ->where('documentable_type', EventParticipant::class)
                ->where('documentable_id', $participant->id)
                ->whereIn('category', $requiredDocuments)
                ->where('status', 'verified')
                ->get();

            foreach ($requiredDocuments as $documentCode) {
                if ($documentTypes->get($documentCode)?->scope === 'team') {
                    if (! $teamDocuments->contains('category', $documentCode)) {
                        return back()->with('error', "Dokumen tim [{$documentCode}] belum diverifikasi.");
                    }

                    continue;
                }

                $coveredMembers = $athleteDocuments->where('category', $documentCode)->pluck('documentable_id')->map(fn ($id): int => (int) $id);
                if ($coveredMembers->unique()->count() !== count(array_unique($memberIds))) {
                    return back()->with('error', "Dokumen atlet [{$documentCode}] belum diverifikasi untuk seluruh roster.");
                }
            }
        }

        if ($flow === 'screening_first' && $hasFee && in_array($participant->status, ['registered', 'revision_needed'], true)) {
            $hours = (int) ($category->payment_time_limit_hours ?? 24);
            $deadline = now()->addHours($hours);
            if ($event->starts_at !== null && $deadline->greaterThan($event->starts_at)) {
                $deadline = $event->starts_at->copy();
            }

            if ($deadline->lessThanOrEqualTo(now())) {
                return back()->with('error', 'Tenggat pembayaran sudah melewati waktu mulai event.');
            }

            DB::transaction(function () use ($participant, $category, $deadline): void {
                $participant->update([
                    'status' => 'pending_payment',
                    'payment_deadline_at' => $deadline,
                    'revision_notes' => null,
                ]);

                Transaction::firstOrCreate(
                    ['payable_type' => 'event_participant', 'payable_id' => $participant->id],
                    [
                        'user_id' => $participant->user_id,
                        'invoice_number' => 'INV-REG-'.strtoupper(Str::random(8)),
                        'type' => 'registration',
                        'amount' => $category->registration_fee,
                        'fee' => 0.00,
                        'total' => $category->registration_fee,
                        'status' => 'pending',
                        'expired_at' => $deadline,
                    ]
                );
            });

            return back()->with('success', "Berkas tim [{$participant->team_name}] lolos verifikasi. Invoice berlaku {$hours} jam dan tidak melewati waktu mulai event.");
        }

        if ($hasFee) {
            $paid = Transaction::query()
                ->where('payable_type', 'event_participant')
                ->where('payable_id', $participant->id)
                ->where('status', 'paid')
                ->exists();
            if (! $paid) {
                return back()->with('error', 'Pendaftaran berbayar belum dapat dikonfirmasi sebelum pembayaran lunas.');
            }
        }

        $participant->update(['status' => 'confirmed', 'revision_notes' => null]);

        return back()->with('success', "Tim [{$participant->team_name}] berhasil diverifikasi dan roster resmi terkunci (Roster Lock).");
    }

    public function requestRevision(Request $request, Event $event, EventParticipant $participant): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        $this->authorizeParticipant($event, $participant);

        $validated = $request->validate([
            'revision_notes' => ['required', 'string', 'max:1000'],
        ]);

        $participant->update([
            'status' => 'revision_needed',
            'revision_notes' => $validated['revision_notes'],
        ]);

        return back()->with('success', "Catatan perbaikan berhasil dikirim ke manajer tim [{$participant->team_name}].");
    }

    public function confirmManualPayment(Event $event, EventParticipant $participant): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        $this->authorizeParticipant($event, $participant);
        $participant->loadMissing(['category', 'members']);

        if ($participant->payment_channel !== 'manual_organizer' || ! $participant->payment_proof || $participant->status !== 'pending_payment') {
            return back()->with('error', 'Bukti pembayaran manual yang aktif wajib tersedia sebelum konfirmasi.');
        }

        $transaction = Transaction::query()
            ->where('payable_type', 'event_participant')
            ->where('payable_id', $participant->id)
            ->where('status', 'pending')
            ->first();
        if (! $transaction || ($transaction->expired_at !== null && now()->greaterThan($transaction->expired_at))) {
            return back()->with('error', 'Invoice pembayaran tidak aktif atau sudah kedaluwarsa.');
        }

        $paidAt = now();
        $flow = (string) $participant->category?->registration_flow;
        if (in_array($flow, ['payment_first', 'by_invitation'], true)
            && ($participant->category?->payment_start === null || $participant->category?->payment_end === null
                || $paidAt->lessThan($participant->category->payment_start) || $paidAt->greaterThan($participant->category->payment_end))) {
            return back()->with('error', 'Pembayaran manual berada di luar jendela pembayaran kategori.');
        }

        DB::transaction(function () use ($participant, $transaction, $paidAt): void {
            $transaction->update(['status' => 'paid', 'paid_at' => $paidAt]);
            $participant->update([
                'status' => $participant->category?->registration_flow === 'screening_first' ? 'confirmed' : 'registered',
                'payment_verified_at' => $paidAt,
                'payment_verified_by' => Auth::id(),
                'revision_notes' => null,
            ]);
        });

        return back()->with('success', "Pembayaran manual tim [{$participant->team_name}] telah diverifikasi lunas.");
    }

    public function destroy(Event $event, EventParticipant $participant): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        $this->authorizeParticipant($event, $participant);

        if (request()->routeIs('admin.*') && ! Auth::user()?->can('event-participant.delete') && ! Auth::user()?->hasRole('superadmin')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki izin untuk menghapus tim peserta.');
        }

        // 1. Cek pembayaran: HANYA yang belum bayar yang boleh dihapus
        $hasPaidTransaction = DB::table('transactions')
            ->where('payable_type', 'event_participant')
            ->where('payable_id', $participant->id)
            ->where('status', 'paid')
            ->exists();

        if ($hasPaidTransaction || $participant->payment_verified_at !== null) {
            return back()->with('error', "Tim [{$participant->team_name}] tidak dapat dihapus karena sudah memiliki pembayaran yang lunas (status paid). Lakukan proses refund atau pembatalan resmi terlebih dahulu.");
        }

        // 2. Cek apakah tim sudah terdaftar dalam jadwal pertandingan atau pool turnamen
        $inPool = $participant->poolTeams()->exists();
        $inGames = $participant->homeGames()->exists() || $participant->awayGames()->exists();
        if ($inPool || $inGames) {
            return back()->with('error', "Tim [{$participant->team_name}] tidak dapat dihapus karena sudah terdaftar dalam jadwal pertandingan atau pool turnamen.");
        }

        // 3. Cek status kompetisi
        if ($participant->status === 'attended') {
            return back()->with('error', "Tim [{$participant->team_name}] tidak dapat dihapus karena sudah berstatus hadir di kompetisi.");
        }

        $teamName = $participant->team_name;
        $category = $participant->category;

        DB::transaction(function () use ($participant, $category): void {
            // Hapus invoice/transaksi pending & expired yang belum terbayar
            DB::table('transactions')
                ->where('payable_type', 'event_participant')
                ->where('payable_id', $participant->id)
                ->whereIn('status', ['pending', 'expired'])
                ->delete();

            // Hapus dokumen tim terkait jika ada
            Document::query()
                ->where('documentable_type', EventParticipant::class)
                ->where('documentable_id', $participant->id)
                ->delete();

            // Reset token undangan terikat jika ada agar tidak tertahan
            DB::table('event_category_invitations')
                ->where('event_participant_id', $participant->id)
                ->update(['event_participant_id' => null, 'used_at' => null]);

            // Hapus tim peserta (anggota roster cascade otomatis via DB FK)
            $participant->delete();

            // Jika kategori memiliki waiting list dan kuota tim terbuka, promosikan antrean #1
            if ($category && $category->enable_waiting_list && $category->max_teams !== null) {
                $activeCount = $category->participants()
                    ->whereIn('status', ['registered', 'revision_needed', 'verified', 'pending_payment', 'confirmed'])
                    ->count();

                if ($activeCount < $category->max_teams) {
                    $nextWaiting = $category->participants()
                        ->where('status', 'waiting_list')
                        ->orderBy('waiting_list_order')
                        ->first();

                    if ($nextWaiting) {
                        $fee = (float) $category->registration_fee;
                        $flow = (string) $category->registration_flow;
                        $invoiceOnAdvance = $fee > 0 && in_array($flow, ['payment_first', 'by_invitation'], true);

                        $nextWaiting->update([
                            'status' => $invoiceOnAdvance ? 'pending_payment' : 'registered',
                            'waiting_list_order' => null,
                            'payment_deadline_at' => $invoiceOnAdvance ? $category->payment_end : null,
                        ]);

                        if ($invoiceOnAdvance) {
                            Transaction::firstOrCreate(
                                ['payable_type' => 'event_participant', 'payable_id' => $nextWaiting->id],
                                [
                                    'user_id' => $nextWaiting->user_id,
                                    'invoice_number' => 'INV-REG-'.strtoupper(Str::random(8)),
                                    'type' => 'registration',
                                    'amount' => $category->registration_fee,
                                    'fee' => 0.00,
                                    'total' => $category->registration_fee,
                                    'status' => 'pending',
                                    'expired_at' => $category->payment_end,
                                ]
                            );
                        }
                    }
                }
            }
        });

        return back()->with('success', "Tim [{$teamName}] berhasil dihapus dari daftar peserta.");
    }

    private function authorizeOrganizer(Event $event): void
    {
        if (! request()->routeIs('admin.*') && $event->user_id !== Auth::id() && ! Auth::user()?->hasRole('superadmin')) {
            abort(403, 'Akses ditolak.');
        }
    }

    private function authorizeParticipant(Event $event, EventParticipant $participant): void
    {
        abort_unless((int) $participant->event_id === (int) $event->id, 404);
    }
}
