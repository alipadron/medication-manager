<?php

namespace App\Models;

use Database\Factories\HomeInventoryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One leftover medication in the home-inventory box: kept separate from the
 * active schedule and tracked with its expiry date.
 *
 * @see CONTEXT.md ("home inventory")
 */
#[Fillable(['name', 'expiry_date'])]
class HomeInventoryEntry extends Model
{
    /** @use HasFactory<HomeInventoryEntryFactory> */
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
        ];
    }
}
