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
            $table->json('allowed_community_subtypes')->nullable()->after('community_subtype');
            $table->json('allowed_institution_types')->nullable()->after('allowed_community_subtypes');
            $table->json('required_entity_documents')->nullable()->after('allowed_institution_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table): void {
            $table->dropColumn([
                'allowed_community_subtypes',
                'allowed_institution_types',
                'required_entity_documents',
            ]);
        });
    }
};
