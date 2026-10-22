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
        Schema::create('school_extracurriculars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->foreignId('sport_category_id')->constrained('sport_categories')->restrictOnDelete();
            $table->unique(['community_id', 'sport_category_id'], 'school_extracurricular_sport_unique');
            $table->timestamps();
        });
        Schema::create('school_extracurricular_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_extracurricular_id')->constrained('school_extracurriculars')->restrictOnDelete();
            $table->foreignId('community_athlete_id')->constrained('community_athletes')->restrictOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->unique(['school_extracurricular_id', 'community_athlete_id'], 'school_extracurricular_member_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_extracurricular_members');
        Schema::dropIfExists('school_extracurriculars');
    }
};
