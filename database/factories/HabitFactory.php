<?php

namespace Database\Factories;

use App\Enums\DefaultHabit;
use App\Models\Habit;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Habit>
 */
class HabitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'team_id' => Team::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    /**
     * Configure the habit as one of the three defaults.
     */
    public function default(DefaultHabit $habit): static
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => $habit->value,
            ...$habit->toAttributes(),
        ]);
    }

    /**
     * Indicate that the habit is no longer tracked.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
