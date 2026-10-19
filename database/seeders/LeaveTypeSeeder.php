<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Seed the default leave types with their standard annual entitlements.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Annual Leave', 'slug' => 'annual', 'default_days' => 14, 'is_paid' => true, 'requires_attachment' => false],
            ['name' => 'Medical Leave', 'slug' => 'medical', 'default_days' => 14, 'is_paid' => true, 'requires_attachment' => true],
            ['name' => 'Emergency Leave', 'slug' => 'emergency', 'default_days' => 3, 'is_paid' => true, 'requires_attachment' => false],
            ['name' => 'Bereavement Leave', 'slug' => 'bereavement', 'default_days' => 3, 'is_paid' => true, 'requires_attachment' => false],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
