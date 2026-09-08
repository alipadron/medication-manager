<?php

namespace App\Models;

use Database\Factories\IntakeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['schedule_id', 'time', 'sort_order'])]
class Intake extends Model
{
    /** @use HasFactory<IntakeFactory> */
    use HasFactory;

    /**
     * Get the schedule this intake belongs to.
     *
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
