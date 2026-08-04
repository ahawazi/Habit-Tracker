<?php

namespace Database\Factories;

use App\Models\DailyCheckIn;
use App\Models\Habit;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<DailyCheckIn>
 */
class DailyCheckInFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'date' => Date::today()->toDateString(),
            'mood_score' => fake()->numberBetween(DailyCheckIn::MOOD_MIN, DailyCheckIn::MOOD_MAX),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Place the check-in on a specific date.
     */
    public function on(CarbonImmutable|string $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'date' => $date instanceof CarbonImmutable ? $date->toDateString() : $date,
        ]);
    }

    /**
     * Record the check-in for a specific user inside a specific team.
     */
    public function forUser(User $user, Team $team): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    /**
     * Leave the mood score unrecorded.
     */
    public function withoutMood(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mood_score' => null,
        ]);
    }

    /**
     * Mark every active habit on the check-in's team as completed.
     */
    public function completed(): static
    {
        return $this->afterCreating(function (DailyCheckIn $checkIn): void {
            $this->recordCompletions($checkIn, true);
        });
    }

    /**
     * Mark exactly the given number of the team's habits as completed.
     */
    public function partiallyCompleted(int $completedCount): static
    {
        return $this->afterCreating(function (DailyCheckIn $checkIn) use ($completedCount): void {
            $habits = $checkIn->team->habits()->activeOrdered()->get();

            $habits->each(function (Habit $habit, int $index) use ($checkIn, $completedCount): void {
                $checkIn->completions()->create([
                    'habit_id' => $habit->id,
                    'completed' => $index < $completedCount,
                ]);
            });
        });
    }

    /**
     * Write a completion row for every active habit on the team.
     */
    protected function recordCompletions(DailyCheckIn $checkIn, bool $completed): void
    {
        $checkIn->team->habits()->activeOrdered()->get()->each(
            fn (Habit $habit) => $checkIn->completions()->create([
                'habit_id' => $habit->id,
                'completed' => $completed,
            ]),
        );
    }
}
