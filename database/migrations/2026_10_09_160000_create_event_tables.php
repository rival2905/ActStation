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
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sport_category_id')->constrained('sport_categories')->restrictOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('banner_image', 255)->nullable();
            $table->enum('status', ['draft', 'published', 'ongoing', 'completed', 'cancelled', 'archived'])->default('draft');
            $table->boolean('registration_open')->default(false);
            $table->integer('max_participants')->nullable();
            $table->decimal('registration_fee', 12, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('registration_deadline')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index('sport_category_id');
        });

        Schema::create('event_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->foreignId('development_level_id')->nullable()->constrained('development_levels')->restrictOnDelete();
            $table->enum('gender', ['male', 'female', 'mixed'])->default('mixed');
            $table->smallInteger('min_roster')->nullable();
            $table->smallInteger('max_roster')->nullable();
            $table->integer('max_teams')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'slug']);
        });

        Schema::create('event_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_category_id')->nullable()->constrained('event_categories')->cascadeOnDelete();
            $table->enum('registrant_type', ['community', 'individual'])->default('community');
            $table->foreignId('community_id')->nullable()->constrained('communities')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('team_name', 255);
            $table->string('logo', 255)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->enum('status', ['registered', 'confirmed', 'cancelled', 'attended'])->default('registered');
            $table->timestamp('registered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_category_id', 'team_name'], 'event_participants_cat_team_unique');
            $table->unique(['event_category_id', 'community_id'], 'event_participants_cat_comm_unique');
            $table->index(['event_id', 'status']);
        });

        Schema::create('event_participant_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_participant_id')->constrained('event_participants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('team_role', 50)->default('player');
            $table->smallInteger('jersey_number')->nullable();
            $table->boolean('is_captain')->default(false);
            $table->string('position', 50)->nullable();
            $table->timestamps();

            $table->unique(['event_participant_id', 'user_id'], 'epm_participant_user_unique');
            $table->unique(['event_participant_id', 'jersey_number'], 'epm_participant_jersey_unique');
        });

        Schema::create('tournament_phases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_category_id')->constrained('event_categories')->cascadeOnDelete();
            $table->string('name', 255);
            $table->enum('phase_type', ['pool', 'knockout', 'placement']);
            $table->integer('phase_order')->default(0);
            $table->enum('status', ['draft', 'generated', 'in_progress', 'completed'])->default('draft');
            $table->json('config')->nullable();
            $table->json('advancement_rules')->nullable();
            $table->timestamps();

            $table->unique(['event_category_id', 'phase_type', 'phase_order'], 'tp_cat_type_order_unique');
        });

        Schema::create('tournament_pools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_phase_id')->constrained('tournament_phases')->cascadeOnDelete();
            $table->string('name', 50);
            $table->integer('pool_order')->default(0);
            $table->timestamps();

            $table->unique(['tournament_phase_id', 'pool_order'], 'tp_phase_order_unique');
        });

        Schema::create('pool_teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_pool_id')->constrained('tournament_pools')->cascadeOnDelete();
            $table->foreignId('event_participant_id')->constrained('event_participants')->cascadeOnDelete();
            $table->integer('seed')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('draws')->default(0);
            $table->integer('losses')->default(0);
            $table->integer('points_for')->default(0);
            $table->integer('points_against')->default(0);
            $table->integer('point_differential')->default(0);
            $table->integer('pool_points')->default(0);
            $table->integer('rank')->nullable();
            $table->timestamps();

            $table->unique(['tournament_pool_id', 'event_participant_id'], 'pool_teams_pool_part_unique');
        });

        Schema::create('games', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_category_id')->constrained('event_categories')->cascadeOnDelete();
            $table->foreignId('tournament_phase_id')->nullable()->constrained('tournament_phases')->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->restrictOnDelete();
            $table->foreignId('venue_court_id')->nullable()->constrained('venue_courts')->nullOnDelete();
            $table->foreignId('team_a_id')->nullable()->constrained('event_participants')->nullOnDelete();
            $table->foreignId('team_b_id')->nullable()->constrained('event_participants')->nullOnDelete();
            $table->string('team_a_label', 100)->nullable();
            $table->string('team_b_label', 100)->nullable();
            $table->timestamp('game_time')->nullable();
            $table->enum('game_status', ['scheduled', 'live', 'finished', 'cancelled', 'bye'])->default('scheduled');
            $table->integer('score_a')->nullable();
            $table->integer('score_b')->nullable();
            $table->string('match_label', 50)->nullable();
            $table->string('round_label', 50)->nullable();
            $table->boolean('is_bye')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'game_status', 'game_time'], 'games_event_status_time_idx');
        });

        Schema::create('bracket_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_category_id')->constrained('event_categories')->cascadeOnDelete();
            $table->foreignId('tournament_phase_id')->constrained('tournament_phases')->cascadeOnDelete();
            $table->enum('phase', ['knockout', 'third_place', 'placement']);
            $table->integer('round');
            $table->integer('match_order');
            $table->enum('node_type', ['match', 'bye', 'placeholder']);
            $table->foreignId('game_id')->nullable()->unique()->constrained('games')->cascadeOnDelete();
            $table->foreignId('next_node_id')->nullable()->constrained('bracket_nodes')->nullOnDelete();
            $table->enum('next_slot', ['a', 'b'])->nullable();
            $table->foreignId('loser_next_node_id')->nullable()->constrained('bracket_nodes')->nullOnDelete();
            $table->enum('loser_next_slot', ['a', 'b'])->nullable();
            $table->string('source_label', 50)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tournament_phase_id', 'round', 'match_order'], 'bn_phase_round_match_unique');
        });

        Schema::create('event_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at');
            $table->integer('duration_minutes')->nullable();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('event_courts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('venue_court_id')->constrained('venue_courts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'venue_court_id']);
        });

        Schema::create('event_schedule_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->time('start_time')->default('08:00');
            $table->time('end_time_daily')->default('21:00');
            $table->unsignedSmallInteger('break_between_games')->default(0);
            $table->json('durations')->nullable();
            $table->timestamps();
        });

        Schema::create('event_schedule_breaks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('label', 100)->nullable();
            $table->timestamps();

            $table->index(['event_id', 'date']);
        });

        Schema::create('game_officials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('duty', 50);
            $table->timestamps();

            $table->unique(['game_id', 'user_id', 'duty'], 'game_officials_game_user_duty_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_officials');
        Schema::dropIfExists('event_schedule_breaks');
        Schema::dropIfExists('event_schedule_settings');
        Schema::dropIfExists('event_courts');
        Schema::dropIfExists('event_schedules');
        Schema::dropIfExists('bracket_nodes');
        Schema::dropIfExists('games');
        Schema::dropIfExists('pool_teams');
        Schema::dropIfExists('tournament_pools');
        Schema::dropIfExists('tournament_phases');
        Schema::dropIfExists('event_participant_members');
        Schema::dropIfExists('event_participants');
        Schema::dropIfExists('event_categories');
        Schema::dropIfExists('events');
    }
};
