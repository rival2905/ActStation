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
        Schema::table('school_authority_changes', function (Blueprint $table): void {
            $table->string('target_email')->nullable()->after('requested_by');
            $table->foreignId('target_user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_authority_changes', function (Blueprint $table): void {
            $table->dropColumn('target_email');
            $table->foreignId('target_user_id')->nullable(false)->change();
        });
    }
};
