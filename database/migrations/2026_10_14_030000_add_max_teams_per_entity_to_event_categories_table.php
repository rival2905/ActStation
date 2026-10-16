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
        Schema::table('event_participants', function (Blueprint $table): void {
            $table->dropUnique('event_participants_cat_comm_unique');
        });

        Schema::table('event_categories', function (Blueprint $table): void {
            $table->unsignedInteger('max_teams_per_entity')->default(1)->after('max_teams');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table): void {
            $table->dropColumn('max_teams_per_entity');
        });

        Schema::table('event_participants', function (Blueprint $table): void {
            $table->unique(['event_category_id', 'community_id'], 'event_participants_cat_comm_unique');
        });
    }
};
