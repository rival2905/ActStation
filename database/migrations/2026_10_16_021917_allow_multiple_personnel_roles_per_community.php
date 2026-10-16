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
        $coach = (int) DB::table('sports_personnel_roles')->where('code', 'coach')->value('id');
        Schema::table('community_coaches', function (Blueprint $table) use ($coach): void {
            $table->unsignedBigInteger('personnel_role_key')->virtualAs("IFNULL(sports_personnel_role_id, {$coach})");
            $table->unique(['community_id', 'user_id', 'personnel_role_key', 'active_coach_flag'], 'community_personnel_active_unique');
        });
        Schema::table('community_coaches', fn (Blueprint $table) => $table->dropUnique('comm_coaches_active_unique'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('community_coaches', function (Blueprint $table): void {
            $table->unique(['community_id', 'user_id', 'active_coach_flag'], 'comm_coaches_active_unique');
            $table->dropUnique('community_personnel_active_unique');
            $table->dropColumn('personnel_role_key');
        });
    }
};
