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
        Schema::create('ticket_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 255);
            $table->decimal('price', 12, 2);
            $table->integer('quota');
            $table->integer('sold_count')->default(0);
            $table->text('description')->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('event_id');
            $table->index('is_active');
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_category_id')->constrained('ticket_categories')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ticket_code', 50)->unique();
            $table->string('qr_code', 255)->unique();
            $table->string('holder_name', 255);
            $table->string('holder_email', 255)->nullable();
            $table->enum('status', ['active', 'pending', 'used', 'cancelled', 'expired'])->default('active');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('ticket_category_id');
            $table->index('transaction_id');
            $table->index('user_id');
            $table->index('status');
        });

        Schema::create('ticket_validations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('validated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('validated_at');
            $table->string('gate', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('ticket_id');
            $table->index('validated_by');
        });

        Schema::create('helpdesk_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 255);
            $table->text('body');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('priority');
        });

        Schema::create('helpdesk_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('helpdesk_ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('helpdesk_ticket_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('helpdesk_replies');
        Schema::dropIfExists('helpdesk_tickets');
        Schema::dropIfExists('ticket_validations');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_categories');
    }
};
