<?php

namespace App\Enums;

/**
 * The fixed set of habits every team tracks in the MVP.
 *
 * The case value is the stable slug persisted on `habits.slug`. It never changes,
 * so translations and tests can rely on it. The English label is only used as the
 * seeded `habits.name` value; the UI renders the translated label instead.
 */
enum DefaultHabit: string
{
    case Exercise = 'exercise';
    case Reading = 'reading';
    case Journaling = 'journaling';

    /**
     * The canonical English name persisted to the database.
     */
    public function label(): string
    {
        return match ($this) {
            self::Exercise => 'Exercise',
            self::Reading => 'Reading',
            self::Journaling => 'Journaling',
        };
    }

    /**
     * The position this habit occupies in every list and form.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Exercise => 1,
            self::Reading => 2,
            self::Journaling => 3,
        };
    }

    /**
     * The icon used to represent this habit in the interface.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Exercise => 'bolt',
            self::Reading => 'book-open',
            self::Journaling => 'pencil-square',
        };
    }

    /**
     * The attributes used to seed this habit for a team.
     *
     * @return array{name: string, sort_order: int, is_active: bool}
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->label(),
            'sort_order' => $this->sortOrder(),
            'is_active' => true,
        ];
    }
}
