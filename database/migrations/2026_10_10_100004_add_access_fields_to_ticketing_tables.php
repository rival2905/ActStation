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
        Schema::table('ticket_categories', function (Blueprint $table): void {
            $table->enum('access_type', ['fullday', 'per_game', 'tournament_pass'])->default('fullday')->after('name');
            $table->enum('seating_tier', ['standard', 'cushioned', 'courtside', 'vip_box'])->default('standard')->after('access_type');
            $table->string('seating_zone', 100)->nullable()->after('seating_tier');
            $table->date('valid_date')->nullable()->after('seating_zone');
            $table->foreignId('game_id')->nullable()->after('valid_date')->constrained('games')->nullOnDelete();
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('seat_number', 20)->nullable()->after('holder_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('seat_number');
        });

        Schema::table('ticket_categories', function (Blueprint $table): void {
            $table->dropForeign(['game_id']);
            $table->dropColumn([
                'access_type',
                'seating_tier',
                'seating_zone',
                'valid_date',
                'game_id',
            ]);
        });
    }
};
