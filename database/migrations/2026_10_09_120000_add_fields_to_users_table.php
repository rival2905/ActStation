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
            $table->string('phone_number', 20)->nullable()->after('email');
            $table->string('phone_normalized', 20)->nullable()->unique()->after('phone_number');
            $table->string('identity_number', 30)->nullable()->unique()->after('phone_normalized');
            $table->string('identity_image', 255)->nullable()->after('identity_number');
            $table->string('birth_certificate', 255)->nullable()->after('identity_image');
            $table->string('birth_place', 255)->nullable()->after('birth_certificate');
            $table->date('birth_date')->nullable()->after('birth_place');
            $table->enum('gender', ['male', 'female'])->nullable()->after('birth_date');
            $table->string('image', 255)->nullable()->after('gender');
            $table->string('external_ref', 64)->nullable()->unique()->after('image');
            $table->boolean('is_active')->default(true)->after('password');
            $table->softDeletes()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'phone_number',
                'phone_normalized',
                'identity_number',
                'identity_image',
                'birth_certificate',
                'birth_place',
                'birth_date',
                'gender',
                'image',
                'external_ref',
                'is_active',
            ]);
        });
    }
};
