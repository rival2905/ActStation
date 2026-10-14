<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('identity_name', 255)->nullable()->after('identity_number');
            $table->string('identity_occupation', 255)->nullable()->after('identity_name');
            $table->string('identity_address', 500)->nullable()->after('identity_occupation');
            $table->string('npwp_number', 16)->nullable()->after('identity_address');
            $table->string('npwp_name', 255)->nullable()->after('npwp_number');
            $table->string('bank_branch', 255)->nullable()->after('bank_account_holder');
            $table->string('province_id', 10)->nullable()->after('bank_branch');
            $table->string('city_id', 10)->nullable()->after('province_id');
            $table->string('subdistrict_id', 15)->nullable()->after('city_id');
            $table->string('village_id', 15)->nullable()->after('subdistrict_id');
            $table->string('postal_code', 5)->nullable()->after('village_id');
            $table->string('address', 500)->nullable()->after('postal_code');
            $table->string('rt', 3)->nullable()->after('address');
            $table->string('rw', 3)->nullable()->after('rt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'identity_name',
                'identity_occupation',
                'identity_address',
                'npwp_number',
                'npwp_name',
                'bank_branch',
                'province_id',
                'city_id',
                'subdistrict_id',
                'village_id',
                'postal_code',
                'address',
                'rt',
                'rw',
            ]);
        });
    }
};
