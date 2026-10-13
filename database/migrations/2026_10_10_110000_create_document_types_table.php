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
        Schema::create('document_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->enum('scope', ['athlete', 'team'])->default('athlete');
            $table->json('applicable_entities')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['scope', 'is_active']);
            $table->index('sort_order');
        });

        Schema::table('event_categories', function (Blueprint $table): void {
            $table->json('required_documents')->nullable()->after('registration_flow');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table): void {
            $table->dropColumn('required_documents');
        });

        Schema::dropIfExists('document_types');
    }
};
