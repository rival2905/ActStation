<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Payment\Models\Transaction;
use App\Modules\Payment\Services\ActivityBillingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;

uses(DatabaseTransactions::class);

test('activity billing snapshots integer amount and replays one invoice', function (): void {
    $user = User::factory()->create();
    $billing = app(ActivityBillingService::class);
    $invoice = $billing->invoice($user->id, 999999, 75000);
    expect($billing->invoice($user->id, 999999, 75000)['id'])->toBe($invoice['id'])
        ->and(Transaction::where('payable_type', 'activity_registration')->where('payable_id', 999999)->count())->toBe(1)
        ->and($billing->paid(999999, 75000))->toBeFalse();
    expect(fn () => $billing->invoice($user->id, 999999, 90000))->toThrow(ValidationException::class);
    expect(fn () => $billing->invoice($user->id, 999998, 0))->toThrow(ValidationException::class);
});
