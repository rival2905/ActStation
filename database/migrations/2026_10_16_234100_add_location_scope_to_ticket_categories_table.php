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
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->string('location_scope', 10)->default('all');
            $table->foreignId('venue_id')->nullable()->constrained('venues')->restrictOnDelete();
            $table->foreignId('venue_court_id')->nullable()->constrained('venue_courts')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->dropForeign(['venue_court_id']);
            $table->dropForeign(['venue_id']);
            $table->dropColumn(['location_scope', 'venue_id', 'venue_court_id']);
        });
    }
};
