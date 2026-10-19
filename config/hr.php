<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Face Recognition
    |--------------------------------------------------------------------------
    |
    | The maximum euclidean distance between two face descriptors for them to
    | be considered a match. Lower values are stricter. face-api.js treats
    | ~0.6 as the standard threshold for the 128-d descriptor.
    |
    */

    'face_match_threshold' => (float) env('FACE_MATCH_THRESHOLD', 0.6),

    /*
    |--------------------------------------------------------------------------
    | Clock Grace Time
    |--------------------------------------------------------------------------
    |
    | Minutes after the operational shift start that a clock-in is still
    | considered on time. Anything later is marked late.
    |
    */

    'late_grace_minutes' => (int) env('ATTENDANCE_LATE_GRACE_MINUTES', 5),

    'late_clock_in' => env('ATTENDANCE_LATE_CLOCK_IN', '09:00'),

];
