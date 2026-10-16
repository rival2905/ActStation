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
        Schema::create('event_committee_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('capabilities');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['event_id', 'name']);
        });
        Schema::create('event_committee_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_committee_role_id')->constrained('event_committee_roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('consented_at');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_committee_role_id', 'user_id']);
            $table->index(['event_id', 'user_id', 'status']);
        });
        Schema::create('event_committee_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_committee_role_id')->constrained('event_committee_roles')->cascadeOnDelete();
            $table->text('token');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_committee_invitations');
        Schema::dropIfExists('event_committee_members');
        Schema::dropIfExists('event_committee_roles');
    }
};
