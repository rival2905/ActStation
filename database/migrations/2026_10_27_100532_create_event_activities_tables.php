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
        Schema::create('event_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('registration_deadline')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('min_members')->default(1);
            $table->unsignedInteger('max_members')->default(1);
            $table->string('fee_mode')->default('separate');
            $table->unsignedInteger('included_slots')->default(0);
            $table->unsignedInteger('fee')->default(0);
            $table->boolean('allow_extra')->default(false);
            $table->boolean('is_open')->default(true);
            $table->timestamps();

            $table->index('event_id');
        });

        Schema::create('activity_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_activity_id')->constrained('event_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_participant_id')->nullable();
            $table->unsignedBigInteger('community_id')->nullable();
            $table->string('team_name')->nullable();
            $table->unsignedInteger('amount')->default(0);
            $table->string('allocation_key')->nullable();
            $table->string('status')->default('pending_review');
            $table->string('request_key')->nullable();

            $table->timestamps();

            $table->index('event_activity_id');
            $table->index('user_id');
            $table->index('parent_participant_id');
            $table->index('allocation_key');
            $table->index('status');
            $table->unique(['event_activity_id', 'user_id', 'request_key'], 'activity_user_request_unique');
        });

        Schema::create('activity_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->constrained('activity_registrations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['registration_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_members');
        Schema::dropIfExists('activity_registrations');
        Schema::dropIfExists('event_activities');
    }
};
