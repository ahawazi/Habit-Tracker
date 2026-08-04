<?php

namespace Database\Factories;

use App\Models\DailyCheckIn;
use App\Models\Habit;
use App\Models\HabitCompletion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HabitCompletion>
 */
class HabitCompletionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'daily_check_in_id' => DailyCheckIn::factory(),
            'habit_id' => Habit::factory(),
            'completed' => false,
        ];
    }

    /**
     * Indicate that the habit was completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'completed' => true,
        ]);
    }
}
