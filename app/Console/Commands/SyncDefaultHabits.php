<?php

namespace App\Console\Commands;

use App\Actions\Habits\EnsureDefaultHabits;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('habits:sync-defaults')]
#[Description('Ensure every team has the three default habits (safe to run repeatedly)')]
class SyncDefaultHabits extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EnsureDefaultHabits $ensureDefaultHabits): int
    {
        $teams = 0;

        Team::query()->chunkById(100, function (Collection $chunk) use ($ensureDefaultHabits, &$teams): void {
            /** @var Team $team */
            foreach ($chunk as $team) {
                $ensureDefaultHabits->handle($team);
                $teams++;
            }
        });

        $this->components->info("Default habits synced for {$teams} team(s).");

        return self::SUCCESS;
    }
}
