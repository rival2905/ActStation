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
        Schema::table('community_activities', function (Blueprint $table): void {
            $table->foreignId('sport_category_id')->nullable()->after('community_id')->constrained('sport_categories')->nullOnDelete();
            $table->string('schedule', 255)->nullable()->after('description');
            $table->string('location', 255)->nullable()->after('schedule');
            $table->boolean('is_active')->default(true)->after('location');
            $table->timestamp('scheduled_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('community_activities', function (Blueprint $table): void {
            $table->dropForeign(['sport_category_id']);
            $table->dropColumn(['sport_category_id', 'schedule', 'location', 'is_active']);
            $table->timestamp('scheduled_at')->nullable(false)->change();
        });
    }
};
