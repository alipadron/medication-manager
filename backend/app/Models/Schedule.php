<?php

namespace App\Models;

use App\Enums\DosingPattern;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['medication_id', 'dosing_pattern', 'interval_hours', 'nominal_start_at', 'safe_interval_minutes'])]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dosing_pattern' => DosingPattern::class,
            'interval_hours' => 'integer',
            'nominal_start_at' => 'datetime',
            'safe_interval_minutes' => 'integer',
        ];
    }

    /**
     * Get the medication this schedule belongs to.
     *
     * @return BelongsTo<Medication, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    /**
     * Get the ordered intakes in this schedule.
     *
     * @return HasMany<Intake, $this>
     */
    public function intakes(): HasMany
    {
        return $this->hasMany(Intake::class)->orderBy('sort_order');
    }

    /**
     * Get the dose events recorded against this schedule.
     *
     * @return HasMany<DoseEvent, $this>
     */
    public function doseEvents(): HasMany
    {
        return $this->hasMany(DoseEvent::class);
    }
}
