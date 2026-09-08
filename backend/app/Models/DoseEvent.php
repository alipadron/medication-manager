<?php

namespace App\Models;

use App\Enums\DoseEventOutcome;
use Database\Factories\DoseEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['intake_id', 'outcome', 'taken_at'])]
class DoseEvent extends Model
{
    /** @use HasFactory<DoseEventFactory> */
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => DoseEventOutcome::class,
            'taken_at' => 'datetime',
        ];
    }

    /**
     * Get the intake this dose event records.
     *
     * @return BelongsTo<Intake, $this>
     */
    public function intake(): BelongsTo
    {
        return $this->belongsTo(Intake::class);
    }
}
