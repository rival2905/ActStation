<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Events\PaymentRefunded;
use App\Contracts\Events\PaymentSucceeded;
use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Payment\Models\Refund;
use App\Modules\Payment\Models\Transaction;
use App\Modules\Payment\Services\RefundService;
use App\Modules\Ticketing\Exceptions\TicketSoldOutException;
use App\Modules\Ticketing\Exceptions\TicketValidationException;
use App\Modules\Ticketing\Models\Ticket;
use App\Modules\Ticketing\Models\TicketCategory;
use App\Modules\Ticketing\Services\HelpdeskService;
use App\Modules\Ticketing\Services\TicketService;
use App\Modules\Ticketing\Services\ValidationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('oversell: issue melebihi kuota ditolak dan sold_count tidak lewat kuota', function (): void {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'VIP Pass',
        'price' => 150000.00,
        'quota' => 2,
        'sold_count' => 0,
        'is_active' => true,
    ]);

    $service = app(TicketService::class);

    $ticket1 = $service->issue($category, $user->id, 'Budi');
    expect($ticket1)->toBeInstanceOf(Ticket::class);
    expect($category->fresh()->sold_count)->toBe(1);

    $ticket2 = $service->issue($category, $user->id, 'Andi');
    expect($ticket2)->toBeInstanceOf(Ticket::class);
    expect($category->fresh()->sold_count)->toBe(2);

    expect(fn () => $service->issue($category, $user->id, 'Cici'))
        ->toThrow(TicketSoldOutException::class);

    expect($category->fresh()->sold_count)->toBe(2);
});

test('validasi tiket dua kali: kedua ditolak dengan pesan yang jelas', function (): void {
    $event = Event::factory()->create();
    $user = User::factory()->create();
    $validator = User::factory()->create();

    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'General Admission',
        'price' => 50000.00,
        'quota' => 10,
        'sold_count' => 0,
    ]);

    $service = app(TicketService::class);
    $validationService = app(ValidationService::class);

    $ticket = $service->issue($category, $user->id, 'Doni');

    $firstValidation = $validationService->validate($ticket->raw_token, $validator->id, 'Gate A');
    expect($firstValidation->ticket_id)->toBe($ticket->id);
    expect($ticket->fresh()->status)->toBe('used');
    expect($ticket->fresh()->used_at)->not->toBeNull();

    expect(fn () => $validationService->validate($ticket->raw_token, $validator->id, 'Gate A'))
        ->toThrow(TicketValidationException::class, 'Tiket sudah digunakan');
});

test('token salah: ditolak dengan exception', function (): void {
    $validator = User::factory()->create();
    $validationService = app(ValidationService::class);

    expect(fn () => $validationService->validate('invalid-token-12345', $validator->id))
        ->toThrow(TicketValidationException::class, 'Tiket tidak ditemukan');
});

test('DB tidak menyimpan raw token', function (): void {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'Presale',
        'price' => 75000.00,
        'quota' => 5,
        'sold_count' => 0,
    ]);

    $service = app(TicketService::class);
    $ticket = $service->issue($category, $user->id, 'Eka');

    $rawToken = $ticket->raw_token;
    expect($rawToken)->not->toBeEmpty();

    // Pastikan hash SHA-256 yang tersimpan
    expect($ticket->qr_code)->toBe(hash('sha256', $rawToken));

    // Raw token tidak ada di DB
    expect(Ticket::where('qr_code', $rawToken)->exists())->toBeFalse();
    expect(Ticket::where('ticket_code', $rawToken)->exists())->toBeFalse();
    expect(Ticket::where('holder_name', $rawToken)->exists())->toBeFalse();
});

test('listener refunded mengembalikan sold_count dan idempoten', function (): void {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'Regular',
        'price' => 100000.00,
        'quota' => 5,
        'sold_count' => 0,
    ]);

    $transactionId = DB::table('transactions')->insertGetId([
        'user_id' => $user->id,
        'invoice_number' => 'INV-TEST-REFUND-001',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 1,
        'amount' => 100000.00,
        'fee' => 0.00,
        'total' => 100000.00,
        'status' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(TicketService::class);
    $ticket = $service->issue($category, $user->id, 'Fajar', null, $transactionId, 'active');
    expect($category->fresh()->sold_count)->toBe(1);
    expect($ticket->fresh()->status)->toBe('active');

    // Refund pertama
    EventFacade::dispatch(new PaymentRefunded(
        transactionId: $transactionId,
        refundId: 1,
        payableType: 'ticket',
        payableId: $ticket->id,
        amount: '100000.00',
    ));

    expect($ticket->fresh()->status)->toBe('cancelled');
    expect($category->fresh()->sold_count)->toBe(0);

    // Refund kedua (harus idempoten, sold_count tidak berkurang di bawah 0)
    EventFacade::dispatch(new PaymentRefunded(
        transactionId: $transactionId,
        refundId: 1,
        payableType: 'ticket',
        payableId: $ticket->id,
        amount: '100000.00',
    ));

    expect($ticket->fresh()->status)->toBe('cancelled');
    expect($category->fresh()->sold_count)->toBe(0);
});

test('listener succeeded mengaktifkan tiket dengan transaction_id tersebut', function (): void {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'Early Bird',
        'price' => 80000.00,
        'quota' => 5,
        'sold_count' => 0,
    ]);

    $transactionId = DB::table('transactions')->insertGetId([
        'user_id' => $user->id,
        'invoice_number' => 'INV-TEST-PAID-001',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 1,
        'amount' => 80000.00,
        'fee' => 0.00,
        'total' => 80000.00,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(TicketService::class);
    $ticket = $service->issue($category, $user->id, 'Gita', null, $transactionId, 'pending');
    expect($ticket->fresh()->status)->toBe('pending');

    EventFacade::dispatch(new PaymentSucceeded(
        transactionId: $transactionId,
        payableType: 'ticket',
        payableId: $ticket->id,
        amount: '80000.00',
    ));

    expect($ticket->fresh()->status)->toBe('active');
});
test('user tanpa ticket-validation.scan dilarang verifikasi (403)', function (): void {
    $role = Role::create(['name' => 'staff_no_scan_'.uniqid(), 'guard_name' => 'web', 'panel_access' => 'internal']);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->post(route('admin.ticket-validations.verify'), [
        'token' => 'dummy-token',
    ]);

    $response->assertForbidden();
});

test('staff dengan ticket-validation.scan berhasil memverifikasi tiket via route', function (): void {
    $staff = User::factory()->create(['is_active' => true]);
    $staff->syncRoles(['staff']);

    $event = Event::factory()->create();
    $buyer = User::factory()->create();

    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'General',
        'price' => 50000.00,
        'quota' => 5,
        'sold_count' => 0,
    ]);

    $service = app(TicketService::class);
    $ticket = $service->issue($category, $buyer->id, 'Hadi');

    $response = $this->actingAs($staff)->post(route('admin.ticket-validations.verify'), [
        'token' => $ticket->raw_token,
        'gate' => 'Gate 1',
    ]);

    $response->assertRedirect(route('admin.ticket-validations.index'));
    $response->assertSessionHas('success');
    expect($ticket->fresh()->status)->toBe('used');
});

test('admin dapat mengelola ticket category', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $event = Event::factory()->create();

    $storeResponse = $this->actingAs($admin)->post(route('admin.ticket-categories.store'), [
        'event_id' => $event->id,
        'name' => 'Gold Pass',
        'price' => 200000,
        'quota' => 50,
        'is_active' => true,
    ]);

    $storeResponse->assertRedirect(route('admin.ticket-categories.index'));
    $category = TicketCategory::where('name', 'Gold Pass')->first();
    expect($category)->not->toBeNull();

    $updateResponse = $this->actingAs($admin)->put(route('admin.ticket-categories.update', $category), [
        'event_id' => $event->id,
        'name' => 'Gold Pass VIP',
        'price' => 250000,
        'quota' => 40,
        'is_active' => true,
    ]);

    $updateResponse->assertRedirect(route('admin.ticket-categories.index'));
    expect($category->fresh()->name)->toBe('Gold Pass VIP');

    $deleteResponse = $this->actingAs($admin)->delete(route('admin.ticket-categories.destroy', $category));
    $deleteResponse->assertRedirect(route('admin.ticket-categories.index'));
    expect(TicketCategory::find($category->id))->toBeNull();
});

test('helpdesk: assign, reply, dan update status bekerja sesuai workflow', function (): void {
    $user = User::factory()->create();
    $staff = User::factory()->create();
    $staff->syncRoles(['staff']);

    $helpdeskService = app(HelpdeskService::class);
    $helpdeskTicket = $helpdeskService->createTicket($user->id, 'Kendala Tiket', 'Tiket belum diterima');

    expect($helpdeskTicket->status)->toBe('open');

    // Assign
    $helpdeskService->assign($helpdeskTicket, $staff->id);
    expect($helpdeskTicket->fresh()->assigned_to)->toBe($staff->id);
    expect($helpdeskTicket->fresh()->status)->toBe('in_progress');

    // Reply
    $reply = $helpdeskService->reply($helpdeskTicket, $staff->id, 'Sedang kami cek');
    expect($reply->body)->toBe('Sedang kami cek');

    // Resolve
    $helpdeskService->updateStatus($helpdeskTicket, 'resolved');
    expect($helpdeskTicket->fresh()->status)->toBe('resolved');
    expect($helpdeskTicket->fresh()->resolved_at)->not->toBeNull();
});

test('validasi via ticket_code ditolak dan tiket cancelled ditolak', function (): void {
    $event = Event::factory()->create();
    $user = User::factory()->create();
    $validator = User::factory()->create();

    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'General',
        'price' => 50000.00,
        'quota' => 5,
        'sold_count' => 0,
    ]);

    $service = app(TicketService::class);
    $validationService = app(ValidationService::class);

    $ticket = $service->issue($category, $user->id, 'Iwan');

    // Validasi via ticket_code harus ditolak karena gate HANYA menerima hash QR token
    expect(fn () => $validationService->validate($ticket->ticket_code, $validator->id, 'Gate B', 'Manual code check'))
        ->toThrow(TicketValidationException::class, 'Tiket tidak ditemukan atau kode tidak valid.');
    expect($ticket->fresh()->status)->toBe('active');

    // Validasi via token asli berhasil
    $validation = $validationService->validate($ticket->raw_token, $validator->id, 'Gate B', 'Token check');
    expect($validation->ticket_id)->toBe($ticket->id);
    expect($ticket->fresh()->status)->toBe('used');

    // Tiket cancelled
    $ticketCancelled = $service->issue($category, $user->id, 'Joko', null, null, 'cancelled');
    expect(fn () => $validationService->validate($ticketCancelled->raw_token, $validator->id))
        ->toThrow(TicketValidationException::class, 'Tiket telah dibatalkan');
});

test('helpdesk controller actions melalui HTTP', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->syncRoles(['admin']);

    $user = User::factory()->create();
    $staff = User::factory()->create(['is_active' => true]);
    $staff->syncRoles(['staff']);

    $helpdeskService = app(HelpdeskService::class);
    $ticket = $helpdeskService->createTicket($user->id, 'Tanya Jadwal', 'Kapan jadwal rilis?');

    // Assign via route
    $assignResponse = $this->actingAs($admin)->post(route('admin.helpdesk.assign', $ticket), [
        'assigned_to' => $staff->id,
    ]);
    $assignResponse->assertRedirect();
    expect($ticket->fresh()->assigned_to)->toBe($staff->id);

    // Reply via route
    $replyResponse = $this->actingAs($staff)->post(route('admin.helpdesk.reply', $ticket), [
        'body' => 'Jadwal rilis besok sore.',
    ]);
    $replyResponse->assertRedirect();
    expect($ticket->fresh()->replies)->toHaveCount(1);

    // Update status via route
    $statusResponse = $this->actingAs($admin)->post(route('admin.helpdesk.status', $ticket), [
        'status' => 'resolved',
    ]);
    $statusResponse->assertRedirect();
    expect($ticket->fresh()->status)->toBe('resolved');
});

test('refund penuh tidak membatalkan tiket used dan sold_count hanya turun untuk tiket non-used', function (): void {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'VIP',
        'price' => 150000.00,
        'quota' => 10,
        'sold_count' => 0,
    ]);

    $transactionId = DB::table('transactions')->insertGetId([
        'user_id' => $user->id,
        'invoice_number' => 'INV-TEST-REFUND-FULL',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 1,
        'amount' => 300000.00,
        'fee' => 0.00,
        'total' => 300000.00,
        'status' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(TicketService::class);
    $ticketActive = $service->issue($category, $user->id, 'Budi Active', null, $transactionId, 'active');
    $ticketUsed = $service->issue($category, $user->id, 'Budi Used', null, $transactionId, 'used');

    expect($category->fresh()->sold_count)->toBe(2);

    EventFacade::dispatch(new PaymentRefunded(
        transactionId: $transactionId,
        refundId: 10,
        payableType: 'ticket',
        payableId: 1,
        amount: '300000.00',
    ));

    expect($ticketActive->fresh()->status)->toBe('cancelled');
    expect($ticketUsed->fresh()->status)->toBe('used');
    expect($category->fresh()->sold_count)->toBe(1);
});

test('refund parsial tidak membatalkan tiket dan tidak dispatch event', function (): void {
    EventFacade::fake([PaymentRefunded::class]);

    $user = User::factory()->create();
    $event = Event::factory()->create();
    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'General',
        'price' => 100000.00,
        'quota' => 10,
        'sold_count' => 0,
    ]);

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'invoice_number' => 'INV-TEST-PARTIAL',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 1,
        'amount' => 200000.00,
        'fee' => 0.00,
        'total' => 200000.00,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $service = app(TicketService::class);
    $ticket = $service->issue($category, $user->id, 'Citra', null, $transaction->id, 'active');

    $refund = Refund::create([
        'transaction_id' => $transaction->id,
        'user_id' => $user->id,
        'amount' => 100000.00,
        'reason' => 'Parsial',
        'status' => 'requested',
    ]);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->syncRoles(['admin']);

    $refundService = app(RefundService::class);
    $refundService->process($refund, 'approve', $admin->id);
    $refundService->process($refund, 'process', $admin->id);

    EventFacade::assertNotDispatched(PaymentRefunded::class);
    expect($ticket->fresh()->status)->toBe('active');
    expect($transaction->fresh()->status)->toBe('paid');
});

test('dispatch succeeded ulang tidak mengubah tiket used', function (): void {
    $event = Event::factory()->create();
    $user = User::factory()->create();
    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'Used Protection',
        'price' => 50000.00,
        'quota' => 5,
        'sold_count' => 0,
    ]);

    $transactionId = DB::table('transactions')->insertGetId([
        'user_id' => $user->id,
        'invoice_number' => 'INV-TEST-USED-001',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 1,
        'amount' => 50000.00,
        'fee' => 0.00,
        'total' => 50000.00,
        'status' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(TicketService::class);
    $ticket = $service->issue($category, $user->id, 'Hadi', null, $transactionId, 'used');
    expect($ticket->fresh()->status)->toBe('used');

    EventFacade::dispatch(new PaymentSucceeded(
        transactionId: $transactionId,
        payableType: 'ticket',
        payableId: $ticket->id,
        amount: '50000.00',
    ));

    expect($ticket->fresh()->status)->toBe('used');
});

test('update kuota dibawah sold_count ditolak', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->syncRoles(['admin']);

    $event = Event::factory()->create();
    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'Festival',
        'price' => 50000.00,
        'quota' => 10,
        'sold_count' => 6,
    ]);

    // Quota 5 is less than sold_count (6) -> validation error
    $response = $this->actingAs($admin)->put(route('admin.ticket-categories.update', $category), [
        'event_id' => $event->id,
        'name' => 'Festival Updated',
        'price' => 50000.00,
        'quota' => 5,
    ]);

    $response->assertSessionHasErrors(['quota']);

    // Quota 6 equal to sold_count -> accepted
    $responseSuccess = $this->actingAs($admin)->put(route('admin.ticket-categories.update', $category), [
        'event_id' => $event->id,
        'name' => 'Festival Updated',
        'price' => 50000.00,
        'quota' => 6,
    ]);

    $responseSuccess->assertSessionHasNoErrors();
    expect($category->fresh()->quota)->toBe(6);
});

test('qr_code tidak muncul di activity_logs dan toArray', function (): void {
    $event = Event::factory()->create();
    $user = User::factory()->create();
    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'Secret VIP',
        'price' => 75000.00,
        'quota' => 5,
        'sold_count' => 0,
    ]);

    $service = app(TicketService::class);
    $ticket = $service->issue($category, $user->id, 'Rian');

    // 1. toArray() hides qr_code
    $array = $ticket->toArray();
    expect(array_key_exists('qr_code', $array))->toBeFalse();

    // 2. ActivityLog redacts qr_code
    $log = ActivityLog::where('auditable_type', $ticket->getMorphClass())
        ->where('auditable_id', $ticket->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->new_values['qr_code'] ?? null)->toBe(ActivityLog::REDACTED);
});

test('assign helpdesk menerima null untuk unassign dan menolak user tanpa role internal', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->syncRoles(['admin']);

    $user = User::factory()->create(['is_active' => true]);
    $externalUser = User::factory()->create(['is_active' => true]);
    $externalUser->assignRole('participant'); // external only

    $staffUser = User::factory()->create(['is_active' => true]);
    $staffUser->assignRole('staff'); // internal

    $helpdeskService = app(HelpdeskService::class);
    $ticket = $helpdeskService->createTicket($user->id, 'Kendala', 'Deskripsi kendala');

    // Assign to staff works
    $resStaff = $this->actingAs($admin)->post(route('admin.helpdesk.assign', $ticket), [
        'assigned_to' => $staffUser->id,
    ]);
    $resStaff->assertSessionHasNoErrors();
    expect($ticket->fresh()->assigned_to)->toBe($staffUser->id);

    // Unassign with empty value / null works
    $resUnassign = $this->actingAs($admin)->post(route('admin.helpdesk.assign', $ticket), [
        'assigned_to' => '',
    ]);
    $resUnassign->assertSessionHasNoErrors();
    expect($ticket->fresh()->assigned_to)->toBeNull();

    // Assigning to external user fails validation
    $resExternal = $this->actingAs($admin)->post(route('admin.helpdesk.assign', $ticket), [
        'assigned_to' => $externalUser->id,
    ]);
    $resExternal->assertSessionHasErrors(['assigned_to']);
});

test('POST verify memakai ticket_code yang valid tetapi tanpa token ditolak dan tiket tetap active', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->syncRoles(['admin']);

    $event = Event::factory()->create();
    $user = User::factory()->create();
    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'VIP',
        'price' => 100000.00,
        'quota' => 10,
        'sold_count' => 0,
        'is_active' => true,
    ]);

    $service = app(TicketService::class);
    $ticket = $service->issue($category, $user->id, 'Agus');
    expect($ticket->status)->toBe('active');

    $response = $this->actingAs($admin)->post(route('admin.ticket-validations.verify'), [
        'token' => $ticket->ticket_code,
        'gate' => 'Gate 1',
    ]);

    $response->assertRedirect(route('admin.ticket-validations.index'));
    $response->assertSessionHas('error');
    expect($ticket->fresh()->status)->toBe('active');
    expect($ticket->fresh()->used_at)->toBeNull();
});
