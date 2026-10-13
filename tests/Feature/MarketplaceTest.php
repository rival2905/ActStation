<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Events\PaymentRefunded;
use App\Contracts\Events\PaymentSucceeded;
use App\Contracts\Payable;
use App\Models\User;
use App\Modules\Marketplace\Exceptions\OutOfStockException;
use App\Modules\Marketplace\Exceptions\ProductUnavailableException;
use App\Modules\Marketplace\Listeners\HandlePaymentRefunded;
use App\Modules\Marketplace\Listeners\HandlePaymentSucceeded;
use App\Modules\Marketplace\Models\Merchant;
use App\Modules\Marketplace\Models\Order;
use App\Modules\Marketplace\Models\Product;
use App\Modules\Marketplace\Models\ProductCategory;
use App\Modules\Marketplace\Services\MerchantService;
use App\Modules\Marketplace\Services\OrderService;
use App\Modules\Marketplace\Services\ProductCategoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole = Role::firstOrCreate(
        ['name' => 'admin', 'guard_name' => 'web'],
        ['panel_access' => 'internal']
    );
    $adminRole->panel_access = 'internal';
    $adminRole->save();

    $staffRole = Role::firstOrCreate(
        ['name' => 'staff', 'guard_name' => 'web'],
        ['panel_access' => 'internal']
    );
    $staffRole->panel_access = 'internal';
    $staffRole->save();

    $permissions = [
        'order.view',
        'order.update',
        'product.view',
        'product.create',
        'product.update',
        'product.delete',
        'product.moderate',
        'product-category.view',
        'product-category.create',
        'product-category.update',
        'product-category.delete',
        'merchant.view',
        'merchant.moderate',
    ];

    foreach ($permissions as $permissionName) {
        $permission = Permission::findOrCreate($permissionName, 'web');
        $adminRole->givePermissionTo($permission);
    }

    $staffRole->givePermissionTo(Permission::findOrCreate('product.view', 'web'));
});

function createTestMerchant(int $userId): Merchant
{
    return Merchant::create([
        'user_id' => $userId,
        'name' => 'Toko Olahraga Jaya',
        'slug' => 'toko-olahraga-jaya-'.uniqid(),
        'phone' => '081234567890',
        'status' => 'active',
    ]);
}

function createTestCategory(): ProductCategory
{
    return ProductCategory::create([
        'name' => 'Jersey Basket',
        'slug' => 'jersey-basket-'.uniqid(),
    ]);
}
function createTestTransaction(int $userId, int $orderId, float $amount): int
{
    return (int) DB::table('transactions')->insertGetId([
        'user_id' => $userId,
        'invoice_number' => 'INV-'.uniqid(),
        'type' => 'order',
        'payable_type' => 'order',
        'payable_id' => $orderId,
        'amount' => $amount,
        'fee' => 0.00,
        'total' => $amount,
        'status' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('stok tidak bisa negatif atau oversell', function (): void {
    $user = User::factory()->create();
    $merchant = createTestMerchant($user->id);
    $category = createTestCategory();

    $product = Product::create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'name' => 'Jersey Timnas',
        'slug' => 'jersey-timnas-'.uniqid(),
        'price' => 250000.00,
        'stock' => 5,
        'status' => 'active',
    ]);

    $orderService = app(OrderService::class);

    // Oversell attempt
    expect(fn () => $orderService->place($user, [
        ['product_id' => $product->id, 'quantity' => 6],
    ]))->toThrow(OutOfStockException::class);

    expect($product->fresh()->stock)->toBe(5);

    // Valid place exact stock
    $order = $orderService->place($user, [
        ['product_id' => $product->id, 'quantity' => 5],
    ]);

    expect($order)->toBeInstanceOf(Order::class);
    $refreshed = $product->fresh();
    expect($refreshed->stock)->toBe(0);
    expect($refreshed->status)->toBe('out_of_stock');

    // Attempting another order when out of stock
    expect(fn () => $orderService->place($user, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]))->toThrow(OutOfStockException::class);
});

test('cancel lalu refund tidak mengembalikan stok dua kali', function (): void {
    $user = User::factory()->create();
    $merchant = createTestMerchant($user->id);
    $category = createTestCategory();

    $product = Product::create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'name' => 'Bola Basket Molten',
        'slug' => 'bola-basket-molten-'.uniqid(),
        'price' => 500000.00,
        'stock' => 10,
        'status' => 'active',
    ]);

    $orderService = app(OrderService::class);
    $order = $orderService->place($user, [
        ['product_id' => $product->id, 'quantity' => 3],
    ]);

    expect($product->fresh()->stock)->toBe(7);

    // Cancel restores stock once
    $orderService->cancel($order);
    expect($product->fresh()->stock)->toBe(10);
    expect($order->fresh()->status)->toBe('cancelled');

    $transactionId = createTestTransaction($user->id, $order->id, 1500000.00);

    // Now call refund listener on already-cancelled order
    $refundListener = app(HandlePaymentRefunded::class);
    $refundListener->handle(new PaymentRefunded(
        transactionId: $transactionId,
        refundId: 1,
        payableType: 'order',
        payableId: $order->id,
        amount: '1500000.00',
    ));

    // Stock must remain 10, not restored again to 13
    expect($product->fresh()->stock)->toBe(10);
    expect($order->fresh()->status)->toBe('refunded');
});

test('payment succeeded dan payment refunded listeners dipanggil 2x idempoten', function (): void {
    $user = User::factory()->create();
    $merchant = createTestMerchant($user->id);
    $category = createTestCategory();

    $product = Product::create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'name' => 'Sepatu Basket Pro',
        'slug' => 'sepatu-basket-pro-'.uniqid(),
        'price' => 1000000.00,
        'stock' => 8,
        'status' => 'active',
    ]);

    $orderService = app(OrderService::class);
    $order = $orderService->place($user, [
        ['product_id' => $product->id, 'quantity' => 2],
    ]);

    expect($product->fresh()->stock)->toBe(6);
    expect($order->fresh()->status)->toBe('pending');

    $transactionId = createTestTransaction($user->id, $order->id, 2000000.00);

    $succeededListener = app(HandlePaymentSucceeded::class);
    $eventSuccess = new PaymentSucceeded(
        transactionId: $transactionId,
        payableType: 'order',
        payableId: $order->id,
        amount: '2000000.00',
    );

    // Call succeeded first time
    $succeededListener->handle($eventSuccess);
    expect($order->fresh()->status)->toBe('paid');
    expect($order->fresh()->transaction_id)->toBe($transactionId);

    // Call succeeded second time (idempotent)
    $succeededListener->handle($eventSuccess);
    expect($order->fresh()->status)->toBe('paid');
    expect($order->fresh()->transaction_id)->toBe($transactionId);

    // Call refunded first time
    $refundListener = app(HandlePaymentRefunded::class);
    $eventRefund = new PaymentRefunded(
        transactionId: $transactionId,
        refundId: 10,
        payableType: 'order',
        payableId: $order->id,
        amount: '2000000.00',
    );

    $refundListener->handle($eventRefund);
    expect($order->fresh()->status)->toBe('refunded');
    expect($product->fresh()->stock)->toBe(8);

    // Call refunded second time (idempotent, stock not incremented again)
    $refundListener->handle($eventRefund);
    expect($order->fresh()->status)->toBe('refunded');
    expect($product->fresh()->stock)->toBe(8);
});

test('siklus kategori ditolak (A ke B ke A)', function (): void {
    $categoryService = app(ProductCategoryService::class);

    $catA = ProductCategory::create([
        'name' => 'Pakaian Olahraga',
        'slug' => 'pakaian-olahraga-'.uniqid(),
        'parent_id' => null,
    ]);

    $catB = ProductCategory::create([
        'name' => 'Jersey',
        'slug' => 'jersey-'.uniqid(),
        'parent_id' => $catA->id,
    ]);

    // Attempting to set parent of A to B (creates A -> B -> A cycle)
    expect(fn () => $categoryService->update($catA, ['parent_id' => $catB->id]))
        ->toThrow(InvalidArgumentException::class);

    // Direct self-parenting attempt (A -> A)
    expect(fn () => $categoryService->update($catA, ['parent_id' => $catA->id]))
        ->toThrow(InvalidArgumentException::class);

    // Verify through HTTP controller
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->put(route('admin.product-categories.update', $catA), [
        'name' => $catA->name,
        'parent_id' => $catB->id,
    ]);

    $response->assertSessionHas('error');
});

test('transisi order ilegal (delivered ke pending) ditolak', function (): void {
    $user = User::factory()->create();
    $merchant = createTestMerchant($user->id);
    $category = createTestCategory();

    $product = Product::create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'name' => 'Kaos Kaki Basket',
        'slug' => 'kaos-kaki-basket-'.uniqid(),
        'price' => 50000.00,
        'stock' => 10,
        'status' => 'active',
    ]);

    $orderService = app(OrderService::class);
    $order = $orderService->place($user, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    // Move to delivered state
    $orderService->updateStatus($order, 'paid');
    $orderService->updateStatus($order, 'processing');
    $orderService->updateStatus($order, 'shipped');
    $orderService->updateStatus($order, 'delivered');
    expect($order->fresh()->status)->toBe('delivered');

    // Attempt illegal transition delivered -> pending
    expect(fn () => $orderService->updateStatus($order, 'pending'))
        ->toThrow(InvalidArgumentException::class);

    expect($order->fresh()->status)->toBe('delivered');

    // Attempt illegal transition delivered -> pending via HTTP
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->put(route('admin.orders.update-status', $order), [
        'status' => 'pending',
    ]);

    $response->assertSessionHas('error');
    expect($order->fresh()->status)->toBe('delivered');
});

test('staff tanpa product.moderate ditolak 403 saat moderate produk', function (): void {
    $user = User::factory()->create();
    $merchant = createTestMerchant($user->id);
    $category = createTestCategory();

    $product = Product::create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'name' => 'Deker Lutut',
        'slug' => 'deker-lutut-'.uniqid(),
        'price' => 75000.00,
        'stock' => 10,
        'status' => 'draft',
    ]);

    $staff = User::factory()->create();
    $staff->assignRole('staff');

    // Staff has only view permission, moderate must return 403
    $response = $this->actingAs($staff)->post(route('admin.products.moderate', $product), [
        'status' => 'active',
    ]);

    $response->assertForbidden();
    expect($product->fresh()->status)->toBe('draft');

    // Admin has product.moderate, must succeed
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $adminResponse = $this->actingAs($admin)->post(route('admin.products.moderate', $product), [
        'status' => 'active',
    ]);

    $adminResponse->assertRedirect();
    expect($product->fresh()->status)->toBe('active');
});

test('total order dihitung dari harga DB bukan input', function (): void {
    $user = User::factory()->create();
    $merchant = createTestMerchant($user->id);
    $category = createTestCategory();

    $product = Product::create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'name' => 'Papan Taktik Pelatih',
        'slug' => 'papan-taktik-pelatih-'.uniqid(),
        'price' => 150000.00,
        'stock' => 5,
        'status' => 'active',
    ]);

    $orderService = app(OrderService::class);

    // Try passing tampered price = 1000 in array
    $order = $orderService->place($user, [
        [
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 1000.00,
        ],
    ]);

    expect((float) $order->total)->toBe(300000.00);
    $item = $order->items->first();
    expect((float) $item->price)->toBe(150000.00);
    expect((float) $item->subtotal)->toBe(300000.00);
});

test('hapus kategori yang dipakai produk ditolak karena foreign key restrict', function (): void {
    $user = User::factory()->create();
    $merchant = createTestMerchant($user->id);
    $category = createTestCategory();

    Product::create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'name' => 'Headband Basket',
        'slug' => 'headband-basket-'.uniqid(),
        'price' => 35000.00,
        'stock' => 20,
        'status' => 'active',
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->delete(route('admin.product-categories.destroy', $category));

    $response->assertSessionHas('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh produk.');
    expect(ProductCategory::where('id', $category->id)->exists())->toBeTrue();
});

test('state machine merchant berjalan sesuai aturan dan menolak transisi ilegal', function (): void {
    $user = User::factory()->create();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Toko Olahraga Pending',
        'slug' => 'toko-olahraga-pending-'.uniqid(),
        'status' => 'pending',
    ]);

    $merchantService = app(MerchantService::class);

    // pending -> active (approved)
    $merchantService->approve($merchant);
    expect($merchant->fresh()->status)->toBe('active');
    expect($merchant->fresh()->verified_at)->not->toBeNull();

    // active -> suspended
    $merchantService->suspend($merchant);
    expect($merchant->fresh()->status)->toBe('suspended');

    // suspended -> active
    $merchantService->activate($merchant);
    expect($merchant->fresh()->status)->toBe('active');

    // active -> closed
    $merchantService->reject($merchant);
    expect($merchant->fresh()->status)->toBe('closed');

    // closed is terminal: approve must throw
    expect(fn () => $merchantService->approve($merchant))
        ->toThrow(InvalidArgumentException::class);
});

test('put status refunded manual ditolak dan status tetap', function (): void {
    $user = User::factory()->create();
    $merchant = createTestMerchant($user->id);
    $category = createTestCategory();

    $product = Product::create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'name' => 'Bola Basket Regresi',
        'slug' => 'bola-basket-regresi-'.uniqid(),
        'price' => 200000.00,
        'stock' => 10,
        'status' => 'active',
    ]);

    $orderService = app(OrderService::class);
    $order = $orderService->place($user, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);
    $orderService->updateStatus($order, 'paid');
    expect($order->fresh()->status)->toBe('paid');

    // Attempt direct service manual transition to refunded
    expect(fn () => $orderService->updateStatus($order, 'refunded'))
        ->toThrow(InvalidArgumentException::class);
    expect($order->fresh()->status)->toBe('paid');

    // Attempt manual transition via HTTP endpoint (flash & validation error)
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->put(route('admin.orders.update-status', $order), [
        'status' => 'refunded',
    ]);
    $response->assertSessionHasErrors('status');
    expect($order->fresh()->status)->toBe('paid');

    // Attempt manual transition via JSON (422)
    $jsonResponse = $this->actingAs($admin)->putJson(route('admin.orders.update-status', $order), [
        'status' => 'refunded',
    ]);
    $jsonResponse->assertStatus(422);
    expect($order->fresh()->status)->toBe('paid');
});

test('place untuk produk draft ditolak dan stok tidak berubah', function (): void {
    $user = User::factory()->create();
    $merchant = createTestMerchant($user->id);
    $category = createTestCategory();

    $product = Product::create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'name' => 'Sepatu Draft Test',
        'slug' => 'sepatu-draft-test-'.uniqid(),
        'price' => 500000.00,
        'stock' => 15,
        'status' => 'draft',
    ]);

    $orderService = app(OrderService::class);

    expect(fn () => $orderService->place($user, [
        ['product_id' => $product->id, 'quantity' => 2],
    ]))->toThrow(ProductUnavailableException::class);

    expect($product->fresh()->stock)->toBe(15);
    expect($product->fresh()->status)->toBe('draft');
});

test('place untuk merchant suspended ditolak dan stok tidak berubah', function (): void {
    $user = User::factory()->create();
    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => 'Toko Suspended',
        'slug' => 'toko-suspended-'.uniqid(),
        'status' => 'suspended',
    ]);
    $category = createTestCategory();

    $product = Product::create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'name' => 'Jersey Suspended Merchant',
        'slug' => 'jersey-suspended-merchant-'.uniqid(),
        'price' => 150000.00,
        'stock' => 20,
        'status' => 'active',
    ]);

    $orderService = app(OrderService::class);

    expect(fn () => $orderService->place($user, [
        ['product_id' => $product->id, 'quantity' => 3],
    ]))->toThrow(ProductUnavailableException::class);

    expect($product->fresh()->stock)->toBe(20);
});

test('Order implements Payable dan getAmount mengembalikan string desimal 2 angka', function (): void {
    $user = User::factory()->create();
    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-PAYABLE-TEST',
        'status' => 'pending',
        'total' => 150000.00,
    ]);

    expect($order)->toBeInstanceOf(Payable::class);
    $amount = $order->getAmount();
    expect($amount)->toBeString()
        ->and($amount)->toBe('150000.00');
});
