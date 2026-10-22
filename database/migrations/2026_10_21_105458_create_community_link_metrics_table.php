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
        Schema::create('community_link_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_link_page_id')->constrained('community_link_pages')->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('item_key', 64);
            $table->unsignedBigInteger('total')->default(0);
            $table->timestamps();

            $table->unique(['community_link_page_id', 'metric_date', 'item_key'], 'uniq_community_link_metrics');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_link_metrics');
    }
};
