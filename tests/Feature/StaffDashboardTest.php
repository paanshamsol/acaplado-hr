<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function staff(array $attributes = []): User
    {
        $staff = User::factory()->create($attributes);
        $staff->assignRole('staff');

        return $staff;
    }

    public function test_the_profile_card_shows_the_photo_company_name_staff_id_and_name(): void
    {
        CompanySetting::current()->update(['name' => 'Acap Lado Sdn Bhd']);

        $staff = $this->staff([
            'name' => 'Alice Example',
            'employee_id' => 'STAFF077',
            'profile_photo_path' => 'profile-photos/avatar.jpg',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Alice Example')
            ->assertSee('STAFF077')
            ->assertSee('Acap Lado Sdn Bhd')
            ->assertSee(Storage::disk('public')->url('profile-photos/avatar.jpg'), false);
    }

    public function test_the_profile_card_falls_back_to_initials_when_no_photo_is_set(): void
    {
        $staff = $this->staff([
            'name' => 'Alice Example',
            'employee_id' => 'STAFF078',
            'profile_photo_path' => null,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('AE');
    }

    public function test_the_profile_card_is_read_only(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF079']);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('maintained by HR and can only be viewed here')
            ->assertDontSee('type="file"', false);
    }
}
