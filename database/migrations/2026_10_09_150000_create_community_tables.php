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
        Schema::create('communities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sport_category_id')
                ->nullable()
                ->constrained('sport_categories')
                ->restrictOnDelete();
            $table->enum('subtype', ['school', 'university', 'institution', 'club', 'free_association'])
                ->default('free_association');
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->string('npsn', 20)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('logo', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('province_id', 10)->nullable();
            $table->string('city_id', 10)->nullable();
            $table->string('subdistrict_id', 15)->nullable();
            $table->string('village_id', 15)->nullable();
            $table->string('postal_code', 5)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->enum('status', ['draft', 'pending', 'verified', 'rejected'])->default('draft');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reject_reason')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('external_ref', 64)->nullable()->unique();
            $table->timestamp('synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'city_id']);
            $table->index('sport_category_id');
        });

        Schema::create('community_officials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('position', 50);
            $table->enum('status', ['pending', 'active', 'rejected', 'inactive'])->default('pending');
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->string('active_official_flag', 100)
                ->nullable()
                ->storedAs('IF(ended_at IS NULL, position, NULL)');

            $table->unique(['community_id', 'user_id', 'active_official_flag'], 'comm_officials_active_unique');
        });

        Schema::create('community_coaches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('development_level_id')->nullable()->index();
            $table->enum('gender_scope', ['male', 'female', 'all'])->default('all');
            $table->string('license', 100)->nullable();
            $table->enum('status', ['pending', 'active', 'rejected', 'inactive'])->default('pending');
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();

            // ponytail: MySQL 1215 forbids ON DELETE SET NULL on base columns of STORED generated columns; virtualAs used.
            $table->string('active_coach_flag', 100)
                ->nullable()
                ->virtualAs("IF(ended_at IS NULL, CONCAT(IFNULL(development_level_id, 'ALL'), '-', gender_scope), NULL)");

            $table->unique(['community_id', 'user_id', 'active_coach_flag'], 'comm_coaches_active_unique');
        });

        Schema::create('community_athletes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('development_level_id')->nullable()->index();
            $table->smallInteger('jersey_number')->nullable();
            $table->string('position', 50)->nullable();
            $table->enum('initiated_by', ['community', 'athlete']);
            $table->enum('status', ['pending', 'active', 'rejected', 'left'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->enum('region_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamp('region_verified_at')->nullable();
            $table->foreignId('region_verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('region_notes')->nullable();
            $table->date('joined_at');
            $table->date('left_at')->nullable();

            // ponytail: MySQL 1215 forbids ON DELETE CASCADE on base columns of STORED generated columns; virtualAs used.
            $table->unsignedBigInteger('active_community_key')
                ->nullable()
                ->virtualAs('IF(left_at IS NULL, community_id, NULL)');

            $table->string('external_ref', 64)->nullable()->unique();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'active_community_key'], 'comm_athletes_active_unique');
            $table->index(['community_id', 'status', 'region_status'], 'comm_athletes_status_idx');
            $table->index(['user_id', 'left_at'], 'comm_athletes_user_left_idx');
        });

        Schema::create('community_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->enum('invite_type', ['athlete', 'official', 'coach'])->default('athlete');
            $table->boolean('is_global')->default(false);
            $table->string('name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->smallInteger('max_registrations')->nullable();
            $table->smallInteger('registrations_count')->default(0);
            $table->enum('status', ['pending', 'registered', 'revoked', 'expired'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->foreignId('registered_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['community_id', 'status']);
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->string('documentable_type', 255);
            $table->unsignedBigInteger('documentable_id');
            $table->string('category', 50);
            $table->string('name', 255);
            $table->string('document_number', 100)->nullable();
            $table->string('file_path', 255);
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id', 'category'], 'documents_target_cat_idx');
            $table->index('status');
        });

        Schema::create('region_overseers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('city_id', 10);
            $table->timestamps();

            $table->unique(['user_id', 'city_id']);
            $table->index('city_id');
        });

        Schema::create('community_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->timestamp('scheduled_at');
            $table->integer('duration_minutes')->nullable();
            $table->integer('max_participants')->nullable();
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();

            $table->index(['community_id', 'scheduled_at']);
            $table->index('status');
        });

        if (! Schema::hasColumn('users', 'synced_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('synced_at')->nullable()->after('external_ref');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_activities');
        Schema::dropIfExists('region_overseers');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('community_invitations');
        Schema::dropIfExists('community_athletes');
        Schema::dropIfExists('community_coaches');
        Schema::dropIfExists('community_officials');
        Schema::dropIfExists('communities');

        if (Schema::hasColumn('users', 'synced_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('synced_at');
            });
        }
    }
};
