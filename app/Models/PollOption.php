<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PollOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $poll_id
 * @property-read string $label
 * @property-read int $position
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read int $votes_count
 */
#[Visible([
    'id',
    'label',
    'votes_count',
])]
final class PollOption extends Model
{
    /** @use HasFactory<PollOptionFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'poll_id' => 'string',
            'label' => 'string',
            'position' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PollVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }
}
