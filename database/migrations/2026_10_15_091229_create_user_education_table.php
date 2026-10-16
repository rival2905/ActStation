<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_educations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->nullable()->constrained('communities')->nullOnDelete();
            $table->string('institution_name');
            $table->string('education_type', 20);
            $table->string('identifier_type', 10);
            $table->text('identifier_value');
            $table->timestamps();
            $table->unique(['user_id', 'community_id', 'education_type'], 'user_education_institution_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_educations');
    }
};
