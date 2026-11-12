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
        Schema::create('social_relay_messages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('channel', 16);
            $table->string('account_ref', 80);
            $table->uuid('delivery_id');
            $table->unsignedBigInteger('producer_id')->index();
            $table->char('payload_hash', 64);
            $table->longText('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->char('claim_hash', 64)->nullable();
            $table->unsignedBigInteger('claimed_by')->nullable();
            $table->timestamp('claim_expires_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'account_ref', 'delivery_id'], 'social_relay_delivery_unique');
            $table->index(['channel', 'account_ref', 'status', 'id'], 'social_relay_pending');
        });

        Schema::create('social_relay_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('message_id')->nullable();
            $table->unsignedBigInteger('principal_id');
            $table->string('action', 32);
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_relay_events');
        Schema::dropIfExists('social_relay_messages');
    }
};
