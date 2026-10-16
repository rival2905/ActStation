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
        Schema::create('draft_personnel_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('registration_draft_id')->constrained('event_registration_drafts')->cascadeOnDelete();
            $table->foreignId('event_category_id')->constrained('event_categories')->cascadeOnDelete();
            $table->text('token');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_personnel_invitations');
    }
};
