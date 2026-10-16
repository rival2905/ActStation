<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sports_personnel_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        foreach (['coach' => 'Coach', 'assistant_coach' => 'Assistant Coach', 'manager' => 'Manager', 'official' => 'Official', 'documentation' => 'Tim Dokumentasi'] as $code => $name) {
            DB::table('sports_personnel_roles')->insert(['code' => $code, 'name' => $name, 'is_active' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('community_coaches', function (Blueprint $table): void {
            $table->foreignId('sports_personnel_role_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('community_coaches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sports_personnel_role_id');
        });
        Schema::dropIfExists('sports_personnel_roles');
    }
};
