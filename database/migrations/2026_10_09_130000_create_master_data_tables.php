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
        Schema::create('sport_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->string('icon', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('development_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sport_category_id')
                ->nullable()
                ->constrained('sport_categories')
                ->nullOnDelete();
            $table->string('name', 100);
            $table->unsignedTinyInteger('min_age')->default(0);
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['sport_category_id', 'sort_order']);
        });

        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255);
            $table->text('address')->nullable();
            $table->string('province_id', 10)->nullable();
            $table->string('city_id', 10)->nullable();
            $table->string('subdistrict_id', 15)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('capacity')->nullable();
            $table->json('facilities')->nullable();
            $table->string('image', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['province_id', 'city_id']);
        });

        Schema::create('venue_courts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')
                ->constrained('venues')
                ->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('surface', 50)->nullable();
            $table->boolean('is_indoor')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['venue_id', 'name']);
        });

        if (! Schema::hasTable('wilayah')) {
            Schema::create('wilayah', function (Blueprint $table): void {
                $table->string('kode', 13)->primary();
                $table->string('nama', 100);
                $table->index('nama', 'wilayah_name_idx');
            });
        }

        if (! Schema::hasTable('wilayah_kodepos')) {
            Schema::create('wilayah_kodepos', function (Blueprint $table): void {
                $table->string('kode', 13)->primary();
                $table->string('kodepos', 5)->nullable();
            });
        }

        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->string('npsn', 20)->unique();
            $table->string('name', 255);
            $table->enum('school_level', ['sd', 'smp', 'sma', 'smk']);
            $table->enum('school_status', ['negeri', 'swasta']);
            $table->string('province_id', 10)->nullable();
            $table->string('city_id', 10)->nullable();
            $table->string('subdistrict_id', 15)->nullable();
            $table->string('village_id', 15)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->smallInteger('dataset_year')->nullable();
            $table->string('source', 50)->default('kemendikdasmen');
            $table->timestamps();

            $table->index(['school_level', 'city_id']);
            $table->index(['province_id', 'city_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
        Schema::dropIfExists('wilayah_kodepos');
        Schema::dropIfExists('wilayah');
        Schema::dropIfExists('venue_courts');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('development_levels');
        Schema::dropIfExists('sport_categories');
    }
};
