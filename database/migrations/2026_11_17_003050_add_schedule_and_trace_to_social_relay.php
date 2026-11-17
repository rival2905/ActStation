<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scheduled dispatch (Office hands over approved replies with their send time) and a per-comment trace for the
     * internal monitoring table. Comment/reply texts in the trace are encrypted; search uses public identifiers only.
     */
    public function up(): void
    {
        Schema::table('social_relay_messages', function (Blueprint $table): void {
            $table->timestamp('not_before')->nullable()->after('expires_at');
        });

        Schema::create('social_relay_traces', function (Blueprint $table): void {
            $table->id();
            $table->string('account_ref', 80);
            $table->string('platform', 16);
            $table->string('comment_id', 80);
            $table->string('media_id', 80)->nullable();
            $table->string('permalink', 300)->nullable();
            $table->string('username', 80)->nullable();
            $table->longText('comment_text')->nullable();
            $table->timestamp('commented_at')->nullable();
            $table->string('status', 24);
            $table->timestamp('received_at')->nullable();
            $table->uuid('comment_delivery_id')->nullable()->index();
            $table->timestamp('office_received_at')->nullable();
            $table->uuid('item_id')->nullable()->index();
            $table->uuid('dispatch_delivery_id')->nullable()->index();
            $table->string('lane', 20)->nullable();
            $table->longText('reply_text')->nullable();
            $table->longText('dm_text')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->string('reply_state', 16)->nullable();
            $table->string('dm_state', 16)->nullable();
            $table->string('reply_id', 80)->nullable();
            $table->string('error_code', 40)->nullable();
            $table->timestamp('result_at')->nullable();
            $table->uuid('receipt_delivery_id')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['account_ref', 'comment_id'], 'social_relay_trace_comment');
            $table->index(['status', 'received_at'], 'social_relay_trace_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_relay_traces');
        Schema::table('social_relay_messages', function (Blueprint $table): void {
            $table->dropColumn('not_before');
        });
    }
};
