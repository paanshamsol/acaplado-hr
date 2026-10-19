<?php

namespace Tests\Unit;

use App\Services\FaceMatcher;
use Tests\TestCase;

class FaceMatcherTest extends TestCase
{
    public function test_identical_descriptors_match_with_zero_distance(): void
    {
        $descriptor = array_fill(0, 128, 0.25);

        $this->assertSame(0.0, FaceMatcher::distance($descriptor, $descriptor));
        $this->assertTrue(FaceMatcher::matches($descriptor, $descriptor));
    }

    public function test_close_descriptors_match(): void
    {
        $stored = array_fill(0, 128, 0.5);
        $captured = array_fill(0, 128, 0.52);

        $this->assertTrue(FaceMatcher::matches($stored, $captured));
    }

    public function test_distant_descriptors_do_not_match(): void
    {
        $stored = array_fill(0, 128, 0.0);
        $captured = array_fill(0, 128, 5.0);

        $this->assertFalse(FaceMatcher::matches($stored, $captured));
    }
}
