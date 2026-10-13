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
        Schema::table('event_categories', function (Blueprint $table): void {
            $table->enum('match_type', ['5x5', '3x3'])->default('5x5')->after('name');
            $table->enum('registration_flow', ['screening_first', 'payment_first', 'instant_free', 'by_invitation'])
                ->default('screening_first')
                ->after('max_teams');
            $table->boolean('enable_waiting_list')->default(true)->after('registration_flow');
            $table->integer('payment_time_limit_hours')->default(24)->after('enable_waiting_list');
            $table->boolean('allow_club')->default(true)->after('payment_time_limit_hours');
            $table->boolean('allow_community')->default(true)->after('allow_club');
            $table->string('community_subtype', 50)->nullable()->after('allow_community');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table): void {
            $table->dropColumn([
                'match_type',
                'registration_flow',
                'enable_waiting_list',
                'payment_time_limit_hours',
                'allow_club',
                'allow_community',
                'community_subtype',
            ]);
        });
    }
};
