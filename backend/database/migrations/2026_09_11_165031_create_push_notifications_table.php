<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per delivered due-dose notification: the no-spam guard. A row
     * is keyed by slot_key — the canonical identity of a due slot
     * ("interval:{schedule}:{dueAt}" for interval meds,
     * "fixed:{intake}:{date}" for fixed-times meds) — and the unique index
     * is the hard guarantee that a slot is notified once, never per cron
     * tick. person_id/schedule_id/intake_id are denormalized for joins.
     */
    public function up(): void
    {
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intake_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slot_key')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};