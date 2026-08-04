<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DailyCheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single user's personal record for a single day within a single team.
 *
 * Rows are created lazily: a date the user has never saved simply has no row and
 * is treated as 0% progress everywhere in the application.
 *
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property CarbonImmutable $date
 * @property int|null $mood_score
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User $user
 * @property-read Collection<int, HabitCompletion> $completions
 */
#[Fillable(['date', 'mood_score', 'notes'])]
class DailyCheckIn extends Model
{
    /** @use HasFactory<DailyCheckInFactory> */
    use HasFactory;

    /**
     * The lowest mood score a user may record.
     */
    public const int MOOD_MIN = 1;

    /**
     * The highest mood score a user may record.
     */
    public const int MOOD_MAX = 10;

    /**
     * Get the team this check-in belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user this check-in belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the per-habit completion records for this check-in.
     *
     * @return HasMany<HabitCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(HabitCompletion::class);
    }

    /**
     * Scope the query to a single user within a single team.
     *
     * Every read path in the tracker goes through this scope, so personal data can
     * never leak across users or teams.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user, Team $team): void
    {
        $query->where('user_id', $user->id)->where('team_id', $team->id);
    }

    /**
     * Scope the query to check-ins falling inside the given inclusive date range.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBetweenDates(Builder $query, CarbonImmutable $start, CarbonImmutable $end): void
    {
        // Compared as dates rather than raw strings: Eloquent writes date columns with a
        // time component, so a bare "Y-m-d" upper bound would exclude the final day.
        $query->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString());
    }

    /**
     * Get the number of habits completed on this check-in.
     *
     * Uses the already-loaded relation when available to avoid an N+1 query.
     */
    public function completedCount(): int
    {
        return $this->relationLoaded('completions')
            ? $this->completions->where('completed', true)->count()
            : $this->completions()->where('completed', true)->count();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'mood_score' => 'integer',
        ];
    }
}
