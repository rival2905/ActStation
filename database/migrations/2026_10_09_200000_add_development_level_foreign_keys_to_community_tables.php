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
        DB::table('community_coaches')
            ->whereNotNull('development_level_id')
            ->whereNotIn('development_level_id', DB::table('development_levels')->select('id'))
            ->update(['development_level_id' => null]);

        DB::table('community_athletes')
            ->whereNotNull('development_level_id')
            ->whereNotIn('development_level_id', DB::table('development_levels')->select('id'))
            ->update(['development_level_id' => null]);

        Schema::table('community_coaches', function (Blueprint $table): void {
            $table->foreign('development_level_id')
                ->references('id')
                ->on('development_levels')
                ->restrictOnDelete();
        });

        Schema::table('community_athletes', function (Blueprint $table): void {
            $table->foreign('development_level_id')
                ->references('id')
                ->on('development_levels')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('community_athletes', function (Blueprint $table): void {
            $table->dropForeign(['development_level_id']);
        });

        Schema::table('community_coaches', function (Blueprint $table): void {
            $table->dropForeign(['development_level_id']);
        });
    }
};
