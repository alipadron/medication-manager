<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scope dose events to a schedule so interval meds (which have no
     * intake grid) can record doses; intake_id stays for fixed-times
     * grid slots and becomes nullable.
     */
    public function up(): void
    {
        Schema::table('dose_events', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable()->after('id');
        });

        // Backfill existing intake-scoped events with their intake's schedule.
        DB::statement(
            'UPDATE dose_events
             SET schedule_id = (SELECT schedule_id FROM intakes WHERE intakes.id = dose_events.intake_id)
             WHERE intake_id IS NOT NULL',
        );

        Schema::table('dose_events', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable(false)->change();
            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->unsignedBigInteger('intake_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dose_events', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropColumn('schedule_id');
            $table->unsignedBigInteger('intake_id')->nullable(false)->change();
        });
    }
};
