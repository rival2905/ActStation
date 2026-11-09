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
        Schema::create('scoreboard_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('community_id')->constrained()->restrictOnDelete();
            $table->foreignId('sport_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('mode', 16);
            $table->string('kind', 16)->default('practice');
            $table->string('home_name', 40);
            $table->string('away_name', 40);
            $table->string('location', 150)->nullable();
            $table->string('status', 16)->default('active');
            $table->json('state');
            $table->json('result')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['community_id', 'sport_category_id', 'status'], 'scoreboard_scope_status');
        });
        Schema::create('scoreboard_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('session_id')->constrained('scoreboard_sessions')->cascadeOnDelete();
            $table->uuid('command_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 60);
            $table->char('fingerprint', 64);
            $table->json('response');
            $table->timestamps();
            $table->unique(['session_id', 'command_id'], 'scoreboard_command_once');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoreboard_commands');
        Schema::dropIfExists('scoreboard_sessions');
    }
};
