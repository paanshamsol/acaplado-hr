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

    /*
    |--------------------------------------------------------------------------
    | Kiosk Window
    |--------------------------------------------------------------------------
    |
    | The kiosk opens this many minutes before the operational shift starts
    | and stays open this many minutes after it ends. For a 17:00 - 06:00
    | shift the kiosk runs 15:30 - 07:30; scans outside that window are
    | rejected so staff can only clock in or out during operation time.
    |
    */

    'attendance_open_before_start_minutes' => (int) env('ATTENDANCE_OPEN_BEFORE_START_MINUTES', 90),

    'attendance_close_after_end_minutes' => (int) env('ATTENDANCE_CLOSE_AFTER_END_MINUTES', 90),

];
