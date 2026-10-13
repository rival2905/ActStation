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
        // ponytail: modul siap dicabut dan referensi berupa soft reference; upgrade path: sinkronisasi event bus lintas service jika modul dipisah repo.
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropForeign(['transaction_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
        });
    }
};
