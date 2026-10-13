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
        Schema::create('event_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedTinyInteger('term')->comment('1 = Termin 1 (40%), 2 = Pelunasan (60%)');
            $table->decimal('amount', 12, 2);
            $table->decimal('platform_fee', 12, 2)->default(0.00);
            $table->decimal('net_amount', 12, 2);
            $table->string('bank_name', 50);
            $table->string('bank_account_number', 50);
            $table->string('bank_account_holder', 255);
            $table->enum('status', ['requested', 'approved', 'transferred', 'rejected'])->default('requested');
            $table->string('transfer_proof', 255)->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('transferred_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_payouts');
    }
};
