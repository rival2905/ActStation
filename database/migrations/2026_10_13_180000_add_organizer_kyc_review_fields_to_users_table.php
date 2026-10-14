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
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('organizer_community_id')->nullable()->after('kyc_rejected_reason');
            $table->timestamp('kyc_submitted_at')->nullable()->after('organizer_community_id');
            $table->timestamp('kyc_reviewed_at')->nullable()->after('kyc_submitted_at');
            $table->unsignedBigInteger('kyc_reviewed_by')->nullable()->after('kyc_reviewed_at');
            $table->timestamp('reviewed_at')->nullable()->after('kyc_reviewed_by');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'organizer_community_id',
                'kyc_submitted_at',
                'kyc_reviewed_at',
                'kyc_reviewed_by',
                'reviewed_at',
                'reviewed_by',
            ]);
        });
    }
};
