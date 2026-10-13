<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Events\PaymentRefunded;
use App\Contracts\Events\PaymentSucceeded;
use App\Models\User;
use App\Modules\Event\Models\Event as EventModel;
use App\Modules\Marketplace\Models\Order;
use App\Modules\Payment\Models\PaymentMethod;
use App\Modules\Payment\Models\Refund;
use App\Modules\Payment\Models\Transaction;
use App\Modules\Payment\Services\RefundService;
use App\Modules\Payment\Services\TransactionService;
use App\Modules\Ticketing\Models\Ticket;
use App\Modules\Ticketing\Models\TicketCategory;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolePermissionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['is_active' => true]);
    $this->staff->assignRole('staff');

    $this->customer = User::factory()->create(['is_active' => true]);
    $this->paymentMethod = PaymentMethod::where('code', 'qris')->first();
});

test('markPaid dua kali dispatch sekali dan idempoten', function (): void {
    Event::fake([PaymentSucceeded::class]);

    $transaction = Transaction::create([
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'invoice_number' => 'INV-20261009-0001',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 101,
        'amount' => 150000.00,
        'fee' => 0.00,
        'total' => 150000.00,
        'status' => 'pending',
    ]);

    $service = app(TransactionService::class);

    $updated1 = $service->markPaid($transaction, 'GW-REF-001');
    expect($updated1->status)->toBe('paid')
        ->and($updated1->paid_at)->not->toBeNull()
        ->and($updated1->gateway_ref)->toBe('GW-REF-001');

    // Second call - idempotent
    $updated2 = $service->markPaid($transaction, 'GW-REF-002');
    expect($updated2->status)->toBe('paid');

    Event::assertDispatched(PaymentSucceeded::class, 1);
    Event::assertDispatched(PaymentSucceeded::class, function (PaymentSucceeded $event) use ($transaction): bool {
        return $event->transactionId === (int) $transaction->id
            && $event->payableType === 'ticket'
            && $event->payableId === 101;
    });
});

test('transisi refund ilegal ditolak', function (): void {
    $transaction = Transaction::create([
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'invoice_number' => 'INV-20261009-0002',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 102,
        'amount' => 200000.00,
        'fee' => 0.00,
        'total' => 200000.00,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $refund = Refund::create([
        'transaction_id' => $transaction->id,
        'user_id' => $this->customer->id,
        'amount' => 100000.00,
        'reason' => 'Batal hadir',
        'status' => 'requested',
    ]);

    $service = app(RefundService::class);

    // Direct process while still 'requested' -> DomainException
    expect(fn () => $service->process($refund, 'process', $this->admin->id))
        ->toThrow(DomainException::class);

    // Reject transition works
    $rejectedRefund = $service->process($refund, 'reject', $this->admin->id);
    expect($rejectedRefund->status)->toBe('rejected');

    // Transitioning rejected refund to approved is illegal
    expect(fn () => $service->process($rejectedRefund, 'approve', $this->admin->id))
        ->toThrow(DomainException::class);
});

test('refund melebihi sisa ditolak', function (): void {
    $transaction = Transaction::create([
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'invoice_number' => 'INV-20261009-0003',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 103,
        'amount' => 100000.00,
        'fee' => 0.00,
        'total' => 100000.00,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $service = app(RefundService::class);

    // Process partial refund: 70.000
    $refund1 = Refund::create([
        'transaction_id' => $transaction->id,
        'user_id' => $this->customer->id,
        'amount' => 70000.00,
        'reason' => 'Sebagian batal',
        'status' => 'requested',
    ]);
    $service->process($refund1, 'approve', $this->admin->id);
    $service->process($refund1, 'process', $this->admin->id);

    // Refund 2 is for 50.000, but only 30.000 remaining
    $refund2 = Refund::create([
        'transaction_id' => $transaction->id,
        'user_id' => $this->customer->id,
        'amount' => 50000.00,
        'reason' => 'Minta refund sisa lebih',
        'status' => 'requested',
    ]);
    // Refund 2 is for 50.000, but only 30.000 remaining -> rejected at approve
    expect(fn () => $service->process($refund2, 'approve', $this->admin->id))
        ->toThrow(DomainException::class);
});

test('refund penuh mengubah status transaksi dan dispatch PaymentRefunded sekali', function (): void {
    Event::fake([PaymentRefunded::class]);

    $transaction = Transaction::create([
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'invoice_number' => 'INV-20261009-0004',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 104,
        'amount' => 100000.00,
        'fee' => 0.00,
        'total' => 100000.00,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $refund = Refund::create([
        'transaction_id' => $transaction->id,
        'user_id' => $this->customer->id,
        'amount' => 100000.00,
        'reason' => 'Refund penuh acara batal',
        'status' => 'requested',
    ]);

    $service = app(RefundService::class);
    $service->process($refund, 'approve', $this->admin->id);
    $processed = $service->process($refund, 'process', $this->admin->id);

    expect($processed->status)->toBe('processed')
        ->and($processed->processed_at)->not->toBeNull()
        ->and($processed->processed_by)->toBe($this->admin->id);

    $transaction->refresh();
    expect($transaction->status)->toBe('refunded');

    Event::assertDispatched(PaymentRefunded::class, 1);
    Event::assertDispatched(PaymentRefunded::class, function (PaymentRefunded $event) use ($transaction, $refund): bool {
        return $event->transactionId === (int) $transaction->id
            && $event->refundId === (int) $refund->id
            && $event->payableType === 'ticket'
            && $event->payableId === 104;
    });
});

test('staff tanpa refund.process menerima 403 saat memproses refund', function (): void {
    $transaction = Transaction::create([
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'invoice_number' => 'INV-20261009-0005',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 105,
        'amount' => 50000.00,
        'fee' => 0.00,
        'total' => 50000.00,
        'status' => 'paid',
    ]);

    $refund = Refund::create([
        'transaction_id' => $transaction->id,
        'user_id' => $this->customer->id,
        'amount' => 50000.00,
        'reason' => 'Kendala jadwal',
        'status' => 'requested',
    ]);
    $role = Role::create(['name' => 'no_refund_proc_'.uniqid(), 'guard_name' => 'web', 'panel_access' => 'internal']);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->post(route('admin.refunds.process', $refund), [
        'action' => 'approve',
    ]);

    $response->assertForbidden();
});

test('admin dengan refund.process berhasil approve dan tolak transisi ilegal via http', function (): void {
    $transaction = Transaction::create([
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'invoice_number' => 'INV-20261009-0006',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 106,
        'amount' => 50000.00,
        'fee' => 0.00,
        'total' => 50000.00,
        'status' => 'paid',
    ]);

    $refund = Refund::create([
        'transaction_id' => $transaction->id,
        'user_id' => $this->customer->id,
        'amount' => 50000.00,
        'reason' => 'Alasan pembatalan',
        'status' => 'requested',
    ]);

    // Admin approves refund
    $response = $this->actingAs($this->admin)->post(route('admin.refunds.process', $refund), [
        'action' => 'approve',
    ]);
    $response->assertSessionHasNoErrors();
    expect($refund->fresh()->status)->toBe('approved');

    // Admin tries illegal transition directly to approve again -> fails with validation error
    $response2 = $this->actingAs($this->admin)->post(route('admin.refunds.process', $refund), [
        'action' => 'approve',
    ]);
    $response2->assertSessionHasErrors(['action']);
});

test('admin dapat mengelola metode pembayaran', function (): void {
    $method = PaymentMethod::where('code', 'bca_va')->firstOrFail();
    expect($method->is_active)->toBeTrue();

    // Toggle active state
    $toggleResponse = $this->actingAs($this->admin)->post(route('admin.payment-methods.toggle', $method));
    $toggleResponse->assertRedirect(route('admin.payment-methods.index'));
    expect($method->fresh()->is_active)->toBeFalse();

    // Update method
    $updateResponse = $this->actingAs($this->admin)->put(route('admin.payment-methods.update', $method), [
        'name' => 'BCA Virtual Account Updated',
        'sort_order' => 10,
    ]);
    $updateResponse->assertRedirect(route('admin.payment-methods.index'));
    expect($method->fresh()->name)->toBe('BCA Virtual Account Updated')
        ->and($method->fresh()->sort_order)->toBe(10);
});

test('admin dan staff dapat melihat daftar dan detail transaksi', function (): void {
    $transaction = Transaction::create([
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'invoice_number' => 'INV-20261009-0007',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 107,
        'amount' => 75000.00,
        'fee' => 0.00,
        'total' => 75000.00,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    // Staff can access index and filter
    $resStaffIndex = $this->actingAs($this->staff)->get(route('admin.transactions.index', [
        'status' => 'paid',
        'type' => 'ticket',
    ]));
    $resStaffIndex->assertOk()
        ->assertSee('INV-20261009-0007');

    // Staff can access show
    $resStaffShow = $this->actingAs($this->staff)->get(route('admin.transactions.show', $transaction));
    $resStaffShow->assertOk();
});

test('staff tanpa refund.view menerima 403 saat akses halaman refunds', function (): void {
    $role = Role::create(['name' => 'no_refund_view_'.uniqid(), 'guard_name' => 'web', 'panel_access' => 'internal']);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);

    $resStaffRefunds = $this->actingAs($user)->get(route('admin.refunds.index'));
    $resStaffRefunds->assertForbidden();
});

test('dua refund yang total-nya melebihi transaksi, refund kedua ditolak saat approve', function (): void {
    $transaction = Transaction::create([
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'total' => 100000.00,
        'amount' => 100000.00,
        'fee' => 0.00,
        'status' => 'paid',
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 999,
        'paid_at' => now(),
    ]);

    $service = app(RefundService::class);

    $refund1 = Refund::create([
        'transaction_id' => $transaction->id,
        'user_id' => $this->customer->id,
        'amount' => 60000.00,
        'reason' => 'Refund pertama 60k',
        'status' => 'requested',
    ]);

    $refund2 = Refund::create([
        'transaction_id' => $transaction->id,
        'user_id' => $this->customer->id,
        'amount' => 50000.00,
        'reason' => 'Refund kedua 50k (total 110k > 100k)',
        'status' => 'requested',
    ]);

    $service->process($refund1, 'approve', $this->admin->id);
    expect($refund1->fresh()->status)->toBe('approved');

    // Refund kedua harus ditolak saat approve karena 60k + 50k > 100k
    expect(fn () => $service->process($refund2, 'approve', $this->admin->id))
        ->toThrow(DomainException::class);
});

test('invoice ter-generate unik dengan format benar', function (): void {
    $trx1 = Transaction::create([
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'total' => 50000.00,
        'amount' => 50000.00,
        'fee' => 0.00,
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 1001,
        'status' => 'pending',
    ]);

    $trx2 = Transaction::create([
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'total' => 50000.00,
        'amount' => 50000.00,
        'fee' => 0.00,
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 1002,
        'status' => 'pending',
    ]);

    expect($trx1->invoice_number)->toMatch('/^INV-\d{8}-[A-Z0-9]{6}$/')
        ->and($trx2->invoice_number)->toMatch('/^INV-\d{8}-[A-Z0-9]{6}$/')
        ->and($trx1->invoice_number)->not->toBe($trx2->invoice_number);
});

test('refund dengan sisa tepat sama diterima dan selisih 0.01 di atas sisa ditolak', function (): void {
    $transaction = Transaction::create([
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'total' => 0.30,
        'amount' => 0.30,
        'fee' => 0.00,
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 999,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $service = app(RefundService::class);

    $refund1 = Refund::create([
        'transaction_id' => $transaction->id,
        'user_id' => $this->customer->id,
        'amount' => 0.10,
        'reason' => 'Refund 0.10',
        'status' => 'requested',
    ]);

    $service->process($refund1, 'approve', $this->admin->id);
    expect($refund1->fresh()->status)->toBe('approved');

    // Refund dengan selisih 0.01 di atas sisa (0.21 > 0.20) ditolak
    $refundOver = Refund::create([
        'transaction_id' => $transaction->id,
        'user_id' => $this->customer->id,
        'amount' => 0.21,
        'reason' => 'Refund 0.21 melebihi sisa',
        'status' => 'requested',
    ]);
    expect(fn () => $service->process($refundOver, 'approve', $this->admin->id))
        ->toThrow(DomainException::class);

    // Refund dengan sisa tepat sama (0.20) diterima
    $refundExact = Refund::create([
        'transaction_id' => $transaction->id,
        'user_id' => $this->customer->id,
        'amount' => 0.20,
        'reason' => 'Refund tepat sisa 0.20',
        'status' => 'requested',
    ]);
    $service->process($refundExact, 'approve', $this->admin->id);
    expect($refundExact->fresh()->status)->toBe('approved');
});

test('setelah migrate menghapus transaksi tidak gagal karena FK dari tickets dan orders', function (): void {
    $transaction = Transaction::create([
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'total' => 100000.00,
        'amount' => 100000.00,
        'fee' => 0.00,
        'type' => 'ticket',
        'payable_type' => 'ticket',
        'payable_id' => 100,
        'status' => 'pending',
    ]);

    $event = EventModel::factory()->create();
    $category = TicketCategory::create([
        'event_id' => $event->id,
        'name' => 'Cat A',
        'price' => 100000.00,
        'quota' => 10,
        'sold_count' => 1,
    ]);

    $ticket = Ticket::create([
        'ticket_category_id' => $category->id,
        'transaction_id' => $transaction->id,
        'user_id' => $this->customer->id,
        'ticket_code' => 'TKT-FK-CHECK',
        'qr_code' => hash('sha256', 'token-fk-check'),
        'holder_name' => 'FK Tester',
        'status' => 'active',
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'transaction_id' => $transaction->id,
        'order_number' => 'ORD-FK-CHECK',
        'status' => 'pending',
        'total' => 100000.00,
    ]);

    $deleted = $transaction->delete();
    expect($deleted)->toBeTrue();
    expect(Transaction::find($transaction->id))->toBeNull();
    expect($ticket->fresh()->transaction_id)->toBe($transaction->id);
    expect($order->fresh()->transaction_id)->toBe($transaction->id);
});
