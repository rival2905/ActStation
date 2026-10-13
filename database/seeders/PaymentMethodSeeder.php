<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Payment\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'code' => 'qris',
                'name' => 'QRIS',
                'type' => 'qris',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'bca_va',
                'name' => 'BCA Virtual Account',
                'type' => 'bank_transfer',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'mandiri_va',
                'name' => 'Mandiri Virtual Account',
                'type' => 'bank_transfer',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'gopay',
                'name' => 'GoPay',
                'type' => 'ewallet',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'code' => 'manual_transfer',
                'name' => 'Transfer Manual',
                'type' => 'bank_transfer',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
