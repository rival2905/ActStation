<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_extracurricular_appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_extracurricular_id')->constrained('school_extracurriculars', indexName: 'school_appointment_unit_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sports_personnel_role_id')->constrained('sports_personnel_roles', indexName: 'school_appointment_role_fk')->restrictOnDelete();
            $table->string('status')->default('pending'); // pending, active, ended
            $table->boolean('can_manage_personnel')->default(false);
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('replaces_id')->nullable()->constrained('school_extracurricular_appointments')->nullOnDelete();
            $table->string('token_hash', 64)->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['school_extracurricular_id', 'status'], 'school_appointment_unit_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_extracurricular_appointments');
    }
};
