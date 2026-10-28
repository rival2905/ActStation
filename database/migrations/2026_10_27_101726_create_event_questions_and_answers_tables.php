<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->string('target', 20);
            $table->string('type', 20);
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable();
            $table->json('school_levels')->nullable();
            $table->json('institution_types')->nullable();
            $table->foreignId('depends_on_question_id')->nullable()->constrained('event_questions')->restrictOnDelete();
            $table->string('conditional_value')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->index(['event_id', 'target', 'order'], 'event_questions_order');
        });
        Schema::create('event_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->string('target', 20);
            $table->unsignedBigInteger('subject_id');
            $table->longText('answers');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['event_id', 'target', 'subject_id'], 'event_answers_subject');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_answers');
        Schema::dropIfExists('event_questions');
    }
};
