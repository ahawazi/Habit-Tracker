<?php

/*
|--------------------------------------------------------------------------
| Habit Tracker Language Lines
|--------------------------------------------------------------------------
|
| Every user-facing string in the habit tracker lives here. Keys are structured
| and language-neutral so a translator can produce lang/fa/habits.php without
| reading the English source, and so the interface can be flipped to RTL without
| touching any Blade template.
|
*/

return [

    'names' => [
        'exercise' => 'Exercise',
        'reading' => 'Reading',
        'journaling' => 'Journaling',
    ],

    'nav' => [
        'dashboard' => 'Dashboard',
        'calendar' => 'Calendar',
        'year_overview' => 'Year Overview',
        'group' => 'Habit Tracker',
    ],

    'dashboard' => [
        'title' => 'Habit Tracker',
        'intro' => 'Track exercise, reading and journaling every day, and watch the streaks add up.',
        'this_week' => 'This week',
        'this_month' => 'This month',
        'current_streak' => 'Current streak',
        'best_streak' => 'Best streak',
        'average_mood' => 'Average mood',
        'average_mood_hint' => 'Across days with a recorded mood this month',
        'completed_days' => ':completed of :total days complete',
        'completed_days_elapsed' => ':completed of :total elapsed days complete',
        'view_calendar' => 'Open calendar',
        'view_year' => 'Open year overview',
    ],

    'check_in' => [
        'title' => 'Daily check-in',
        'today_title' => "Today's check-in",
        'subtitle' => 'Mark what you completed, record how you felt, and add a note.',
        'habits_label' => 'Habits',
        'mood_label' => 'Mood',
        'mood_description' => 'Optional. Rate how you felt from 1 to 10.',
        'mood_clear' => 'Clear mood',
        'mood_none' => 'Not recorded',
        'notes_label' => 'Notes',
        'notes_placeholder' => 'Anything worth remembering about today?',
        'notes_description' => 'Optional.',
        'save' => 'Save check-in',
        'saved' => 'Check-in saved.',
        'previous_day' => 'Previous day',
        'next_day' => 'Next day',
        'go_to_today' => 'Go to today',
        'future_notice' => 'This date is in the future. You can look ahead, but habits and mood can only be recorded once the day arrives.',
        'progress' => 'Daily progress',
        'progress_value' => ':percentage% complete',
        'habits_completed' => ':completed of :total habits',
    ],

    'week' => [
        'title' => 'This week',
        'subtitle' => 'Pick a day to open its check-in.',
        'open_day' => 'Open the check-in for :date',
    ],

    'calendar' => [
        'title' => 'Monthly calendar',
        'subtitle' => 'Every day at a glance. Select a date to open its check-in.',
        'previous_month' => 'Previous month',
        'next_month' => 'Next month',
        'month_summary' => ':completed of :total days complete',
        'month_summary_elapsed' => ':completed of :total elapsed days complete',
        'month_future' => 'This month hasn\'t started yet — check back once it arrives.',
        'empty_day' => 'No check-in recorded',
        'today_badge' => 'Today',
    ],

    'year' => [
        'title' => 'Year overview',
        'subtitle' => 'From today through :end.',
        'overall_completion' => 'Overall completion',
        'completed_days' => 'Completed days',
        'current_streak' => 'Current streak',
        'best_streak' => 'Best streak',
        'legend' => 'Progress legend',
    ],

    'levels' => [
        '0' => 'No habits completed',
        '1' => 'One habit completed',
        '2' => 'Two habits completed',
        '3' => 'All habits completed',
    ],

    'day_summary' => [
        'with_mood' => ':date — :completed of :total habits (:percentage%), mood :mood of :max',
        'without_mood' => ':date — :completed of :total habits (:percentage%)',
        'future' => ':date — upcoming',
    ],

    'streak' => [
        'days' => '{0} No days yet|{1} :count day|[2,*] :count days',
        'rule' => 'A day counts towards a streak only when all three habits are completed.',
    ],

    'validation' => [
        'future_date' => 'You cannot record a check-in for a future date.',
        'mood_range' => 'The mood score must be a whole number between :min and :max.',
        'invalid_date' => 'That date is not part of the tracked period.',
        'notes_max' => 'Notes cannot be longer than :max characters.',
    ],

    'empty' => [
        'no_habits' => 'This team has no habits yet. Run the habits:sync-defaults command to create them.',
    ],

];
