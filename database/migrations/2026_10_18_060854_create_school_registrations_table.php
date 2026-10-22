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
        Schema::create('school_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('npsn', 16)->index();
            $table->string('contact_email');
            $table->string('email_hash', 64)->index();
            $table->string('email_token_hash', 64)->nullable()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_disk', 32)->default('local');
            $table->string('status', 32)->default('pending')->index();
            $table->string('admin_review_status', 32)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('event_category_id')->nullable()->constrained('event_categories')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['community_id', 'user_id']);
            $table->index(['npsn', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_registrations');
    }
};
