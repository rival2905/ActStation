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
        Schema::create('community_link_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->restrictOnDelete();
            $table->string('handle', 40)->unique();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('draft');
            $table->json('draft_content');
            $table->json('published_content')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable();
            $table->index(['community_id', 'is_primary']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_link_pages');
    }
};
