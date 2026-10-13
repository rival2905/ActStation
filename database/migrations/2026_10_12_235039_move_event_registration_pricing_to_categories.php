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
        $conflictingCategoryIds = DB::table('event_categories')
            ->join('events', 'events.id', '=', 'event_categories.event_id')
            ->where('event_categories.registration_flow', 'instant_free')
            ->where('events.registration_fee', '>', 0)
            ->orderBy('event_categories.id')
            ->limit(20)
            ->pluck('event_categories.id');

        if ($conflictingCategoryIds->isNotEmpty()) {
            throw new RuntimeException(
                'Pricing cutover blocked: instant_free categories inherit a paid event fee. '
                .'Audit category IDs ['.$conflictingCategoryIds->implode(', ').'] and explicitly correct their flow before rerunning migration. '
                .'No participant status or historical invoice was changed.'
            );
        }
        $conflictingPaymentFirstIds = DB::table('event_categories')
            ->join('events', 'events.id', '=', 'event_categories.event_id')
            ->where('event_categories.registration_flow', 'payment_first')
            ->where(function ($q): void {
                $q->whereNull('events.registration_fee')->orWhere('events.registration_fee', '<=', 0);
            })
            ->orderBy('event_categories.id')
            ->limit(20)
            ->pluck('event_categories.id');

        if ($conflictingPaymentFirstIds->isNotEmpty()) {
            throw new RuntimeException(
                'Pricing cutover blocked: payment_first categories require registration_fee > 0. '
                .'Audit category IDs ['.$conflictingPaymentFirstIds->implode(', ').'] and explicitly correct their flow or fee before rerunning migration. '
                .'No participant status or historical invoice was changed.'
            );
        }

        Schema::table('event_categories', function (Blueprint $table): void {
            $table->unsignedSmallInteger('min_age')->nullable()->after('development_level_id');
            $table->unsignedSmallInteger('max_age')->nullable()->after('min_age');
            $table->decimal('registration_fee', 12, 2)->default(0)->after('max_age');
            $table->dateTime('registration_start')->nullable()->after('registration_fee');
            $table->dateTime('registration_end')->nullable()->after('registration_start');
            $table->dateTime('payment_start')->nullable()->after('registration_end');
            $table->dateTime('payment_end')->nullable()->after('payment_start');
            $table->integer('payment_time_limit_hours')->nullable()->default(null)->change();
        });

        DB::table('event_categories')->update([
            'registration_fee' => DB::raw('(SELECT events.registration_fee FROM events WHERE events.id = event_categories.event_id)'),
        ]);

        $failedBackfills = DB::table('event_categories')
            ->join('events', 'events.id', '=', 'event_categories.event_id')
            ->whereColumn('event_categories.registration_fee', '!=', 'events.registration_fee')
            ->count();

        if ($failedBackfills !== 0) {
            Schema::table('event_categories', function (Blueprint $table): void {
                $table->dropColumn([
                    'min_age',
                    'max_age',
                    'registration_fee',
                    'registration_start',
                    'registration_end',
                    'payment_start',
                    'payment_end',
                ]);
            });

            DB::table('event_categories')->whereNull('payment_time_limit_hours')->update(['payment_time_limit_hours' => 24]);
            Schema::table('event_categories', function (Blueprint $table): void {
                $table->integer('payment_time_limit_hours')->default(24)->nullable(false)->change();
            });

            throw new RuntimeException("Pricing cutover blocked: {$failedBackfills} category fees failed verification; events.registration_fee was retained and category columns were removed.");
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('registration_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'This pricing cutover is irreversible: one event fee cannot faithfully restore independently edited category fees, '
            .'and events without categories have no recoverable legacy value. Restore from a pre-cutover backup instead.'
        );
    }
};
