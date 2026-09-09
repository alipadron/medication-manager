<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the dosing-pattern fields to schedules: the pattern itself,
     * interval_hours + nominal start alignment for interval meds, and
     * safe_interval_minutes as the floor for fixed-times meds only.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('dosing_pattern')->default('fixed_times')->after('medication_id');
            $table->unsignedInteger('interval_hours')->nullable()->after('dosing_pattern');
            $table->timestamp('nominal_start_at')->nullable()->after('interval_hours');
            $table->unsignedInteger('safe_interval_minutes')->default(240)->after('nominal_start_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['dosing_pattern', 'interval_hours', 'nominal_start_at', 'safe_interval_minutes']);
        });
    }
};
