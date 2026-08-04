<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tracker End Date
    |--------------------------------------------------------------------------
    |
    | The last date the visual tracker covers. The tracker always starts from the
    | current date, so this value alone defines the navigable window used by the
    | monthly calendar and the year overview.
    |
    */

    'ends_on' => env('HABIT_TRACKER_ENDS_ON', '2026-12-31'),

];
