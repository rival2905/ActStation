<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->json('personnel_requirements')->nullable();
        });
        Schema::create('event_participant_personnel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Role identity belongs to the personnel contract; snapshot survives master deactivation.
            $table->unsignedBigInteger('role_id');
            $table->string('role_name');
            $table->timestamps();
            $table->unique(['event_participant_id', 'user_id', 'role_id'], 'participant_personnel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participant_personnel');
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('personnel_requirements');
        });
    }
};
