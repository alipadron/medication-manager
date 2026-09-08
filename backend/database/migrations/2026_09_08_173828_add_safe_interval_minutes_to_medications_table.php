<?php

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
        Schema::table('medications', function (Blueprint $table) {
            // Minimum minutes that must pass between two intakes of this
            // medication after a shift. The shift engine never re-times two
            // intakes closer than this. Sensible default: 4 hours.
            $table->unsignedInteger('safe_interval_minutes')->default(240);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->dropColumn('safe_interval_minutes');
        });
    }
};
