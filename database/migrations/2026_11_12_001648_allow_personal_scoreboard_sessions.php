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
        Schema::table('scoreboard_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('community_id')->nullable()->change();
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });
        Schema::table('scoreboard_commands', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('scoreboard_sessions')->whereNull('community_id')->exists()
            || DB::table('scoreboard_sessions')->whereNull('created_by')->exists()
            || DB::table('scoreboard_commands')->whereNull('actor_id')->exists()) {
            throw new RuntimeException('Personal scoreboards must be retained; cannot restore non-null constraints.');
        }
        Schema::table('scoreboard_commands', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->nullable(false)->change();
        });
        Schema::table('scoreboard_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('community_id')->nullable(false)->change();
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
        });
    }
};
