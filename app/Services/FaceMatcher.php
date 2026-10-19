<?php

namespace App\Services;

class FaceMatcher
{
    /**
     * Euclidean distance between two face descriptors.
     *
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    public static function distance(array $a, array $b): float
    {
        $length = min(count($a), count($b));
        $sum = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $sum += ($a[$i] - $b[$i]) ** 2;
        }

        return sqrt($sum);
    }

    /**
     * Determine whether two descriptors are the same person.
     *
     * @param  array<int, float|int>  $stored
     * @param  array<int, float|int>  $captured
     */
    public static function matches(array $stored, array $captured, ?float $threshold = null): bool
    {
        $threshold ??= (float) config('hr.face_match_threshold', 0.6);

        return static::distance($stored, $captured) <= $threshold;
    }
}
