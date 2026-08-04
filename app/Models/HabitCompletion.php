<?php

namespace App\Models;

use Database\Factories\HabitCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $daily_check_in_id
 * @property int $habit_id
 * @property bool $completed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DailyCheckIn $dailyCheckIn
 * @property-read Habit $habit
 */
/**
 * `habit_id` is fillable because the only writer, SaveDailyCheckIn, resolves it from
 * the owning team's habit list rather than from request input. The ownership columns
 * on DailyCheckIn stay guarded.
 */
#[Fillable(['habit_id', 'completed'])]
class HabitCompletion extends Model
{
    /** @use HasFactory<HabitCompletionFactory> */
    use HasFactory;

    /**
     * Get the check-in this completion belongs to.
     *
     * @return BelongsTo<DailyCheckIn, $this>
     */
    public function dailyCheckIn(): BelongsTo
    {
        return $this->belongsTo(DailyCheckIn::class);
    }

    /**
     * Get the habit this completion refers to.
     *
     * @return BelongsTo<Habit, $this>
     */
    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
        ];
    }
}
