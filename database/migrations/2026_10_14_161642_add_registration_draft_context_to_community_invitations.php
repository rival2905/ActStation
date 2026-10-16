<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_invitations', function (Blueprint $table): void {
            $table->uuid('registration_draft_id')->nullable()->after('community_id');
            $table->foreignId('event_category_id')->nullable()->after('registration_draft_id');

            $table->foreign('registration_draft_id', 'community_invitations_draft_fk')
                ->references('id')->on('event_registration_drafts')->cascadeOnDelete();
            $table->foreign('event_category_id', 'community_invitations_category_fk')
                ->references('id')->on('event_categories')->cascadeOnDelete();
            $table->index(['registration_draft_id', 'event_category_id'], 'community_invitations_draft_category_idx');
        });

        Schema::create('registration_draft_candidates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('registration_draft_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('community_invitation_id')->constrained('community_invitations')->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('registration_draft_id', 'registration_draft_candidates_draft_fk')
                ->references('id')->on('event_registration_drafts')->cascadeOnDelete();
            $table->unique(['registration_draft_id', 'user_id'], 'registration_draft_candidates_draft_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_draft_candidates');

        Schema::table('community_invitations', function (Blueprint $table): void {
            $table->dropForeign('community_invitations_draft_fk');
            $table->dropForeign('community_invitations_category_fk');
            $table->dropIndex('community_invitations_draft_category_idx');
            $table->dropColumn(['registration_draft_id', 'event_category_id']);
        });
    }
};
