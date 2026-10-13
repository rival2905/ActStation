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
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('draft', 'pending_approval', 'published', 'ongoing', 'completed', 'cancelled', 'archived') NOT NULL DEFAULT 'draft'");

        Schema::table('events', function (Blueprint $table): void {
            $table->string('venue_approval_doc', 255)->nullable()->after('banner_image');
            $table->text('kyc_notes')->nullable()->after('cancel_reason');
            $table->enum('payout_status', ['none', 'term1_requested', 'term1_paid', 'term2_requested', 'settled', 'disputed'])->default('none')->after('status');
            $table->boolean('allow_manual_payment')->default(false)->after('payout_status');
            $table->string('manual_bank_name', 50)->nullable()->after('allow_manual_payment');
            $table->string('manual_bank_account_number', 50)->nullable()->after('manual_bank_name');
            $table->string('manual_bank_account_holder', 255)->nullable()->after('manual_bank_account_number');
            $table->text('manual_payment_instructions')->nullable()->after('manual_bank_account_holder');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn([
                'venue_approval_doc',
                'kyc_notes',
                'payout_status',
                'allow_manual_payment',
                'manual_bank_name',
                'manual_bank_account_number',
                'manual_bank_account_holder',
                'manual_payment_instructions',
            ]);
        });

        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('draft', 'published', 'ongoing', 'completed', 'cancelled', 'archived') NOT NULL DEFAULT 'draft'");
    }
};
