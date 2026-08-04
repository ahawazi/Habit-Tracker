<?php

namespace App\Models;

use App\Enums\DefaultHabit;
use Database\Factories\HabitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, HabitCompletion> $completions
 */
#[Fillable(['name', 'slug', 'sort_order', 'is_active'])]
class Habit extends Model
{
    /** @use HasFactory<HabitFactory> */
    use HasFactory;

    /**
     * Get the team that owns the habit.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the completion records for this habit.
     *
     * @return HasMany<HabitCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(HabitCompletion::class);
    }

    /**
     * Scope the query to habits belonging to the given team.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->where('team_id', $team->id);
    }

    /**
     * Scope the query to active habits, in display order.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActiveOrdered(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the translated display name, falling back to the stored English name.
     *
     * The fallback means habits created outside the default set (a future feature)
     * still render correctly without a dedicated translation key.
     */
    public function displayName(): string
    {
        $key = "habits.names.{$this->slug}";

        return Lang::has($key) ? __($key) : $this->name;
    }

    /**
     * Get the icon representing this habit.
     */
    public function icon(): string
    {
        return DefaultHabit::tryFrom($this->slug)?->icon() ?? 'check-circle';
    }
}
