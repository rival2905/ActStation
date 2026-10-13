<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE event_participants MODIFY COLUMN status ENUM('registered', 'revision_needed', 'verified', 'pending_payment', 'waiting_list', 'confirmed', 'rejected', 'cancelled', 'attended') NOT NULL DEFAULT 'registered'");

        Schema::table('event_participants', function (Blueprint $table): void {
            $table->enum('payment_channel', ['gateway_escrow', 'manual_organizer'])->default('gateway_escrow')->after('status');
            $table->string('payment_proof', 255)->nullable()->after('payment_channel');
            $table->timestamp('payment_verified_at')->nullable()->after('payment_proof');
            $table->foreignId('payment_verified_by')->nullable()->after('payment_verified_at')->constrained('users')->nullOnDelete();
            $table->boolean('is_escrow_waived')->default(false)->after('payment_verified_by');
            $table->timestamp('waiver_signed_at')->nullable()->after('is_escrow_waived');
            $table->string('waiver_ip', 45)->nullable()->after('waiver_signed_at');
            $table->string('waiver_user_agent', 255)->nullable()->after('waiver_ip');
            $table->text('revision_notes')->nullable()->after('notes');
            $table->integer('waiting_list_order')->nullable()->after('revision_notes');
            $table->timestamp('payment_deadline_at')->nullable()->after('registered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table): void {
            $table->dropForeign(['payment_verified_by']);
            $table->dropColumn([
                'payment_channel',
                'payment_proof',
                'payment_verified_at',
                'payment_verified_by',
                'is_escrow_waived',
                'waiver_signed_at',
                'waiver_ip',
                'waiver_user_agent',
                'revision_notes',
                'waiting_list_order',
                'payment_deadline_at',
            ]);
        });

        DB::statement("ALTER TABLE event_participants MODIFY COLUMN status ENUM('registered', 'confirmed', 'cancelled', 'attended') NOT NULL DEFAULT 'registered'");
    }
};
