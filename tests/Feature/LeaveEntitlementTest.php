<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
    }

    public function test_leave_type_seeder_creates_default_types(): void
    {
        $this->assertSame(4, LeaveType::count());

        $this->assertDatabaseHas('leave_types', [
            'slug' => 'annual',
            'default_days' => 14,
        ]);

        $this->assertDatabaseHas('leave_types', [
            'slug' => 'medical',
            'requires_attachment' => true,
        ]);
    }

    public function test_admin_can_view_leave_entitlements(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.leaves.index'))
            ->assertOk()
            ->assertSee('Annual Leave');
    }

    public function test_hr_can_view_leave_entitlements(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        $this->actingAs($hr)
            ->get(route('admin.leaves.index'))
            ->assertOk();
    }

    public function test_leave_matrix_lists_used_and_remaining_for_hr_and_admin(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Person']);
        $admin->assignRole('admin');

        $hr = User::factory()->create(['name' => 'HR Person']);
        $hr->assignRole('hr');

        $staff = User::factory()->create(['name' => 'Staff Person']);
        $staff->assignRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();
        $year = (int) now()->year;

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => $year,
            'entitlement' => 14,
            'used' => 6,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leaves.index', ['year' => $year]))
            ->assertOk()
            ->assertSee('Admin Person')
            ->assertSee('HR Person')
            ->assertSee('Staff Person')
            ->assertSee('Used')
            ->assertSee('Left');
    }

    public function test_staff_cannot_view_leave_entitlements(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('admin.leaves.index'))
            ->assertForbidden();
    }

    public function test_admin_can_update_leave_entitlement(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();
        $year = (int) now()->year;

        $this->actingAs($admin)
            ->put(route('admin.leaves.update', $staff), [
                'year' => $year,
                'entitlements' => [$annual->id => 18],
                'used' => [$annual->id => 5],
            ])
            ->assertRedirect(route('admin.leaves.index', ['year' => $year]));

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => $year,
            'entitlement' => 18,
            'used' => 5,
        ]);
    }

    public function test_admin_can_record_used_and_carried_over_leave(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();
        $year = (int) now()->year;

        $this->actingAs($admin)
            ->put(route('admin.leaves.update', $staff), [
                'year' => $year,
                'entitlements' => [$annual->id => 14],
                'carried_over' => [$annual->id => 3],
                'used' => [$annual->id => 6],
            ])
            ->assertRedirect(route('admin.leaves.index', ['year' => $year]));

        $balance = LeaveBalance::where('user_id', $staff->id)
            ->where('leave_type_id', $annual->id)
            ->firstOrFail();

        $this->assertSame('6.0', $balance->used);
        $this->assertSame('3.0', $balance->carried_over);
        $this->assertSame(11.0, $balance->remaining());
    }

    public function test_admin_can_delete_a_single_leave_entitlement_via_checkbox(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();
        $medical = LeaveType::where('slug', 'medical')->firstOrFail();
        $year = (int) now()->year;

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => $year,
            'entitlement' => 14,
        ]);

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $medical->id,
            'year' => $year,
            'entitlement' => 14,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.leaves.update', $staff), [
                'year' => $year,
                'entitlements' => [
                    $annual->id => 14,
                    $medical->id => 14,
                ],
                'delete' => [
                    $annual->id => 1,
                ],
            ])
            ->assertRedirect(route('admin.leaves.index', ['year' => $year]));

        $this->assertDatabaseMissing('leave_balances', [
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $staff->id,
            'leave_type_id' => $medical->id,
        ]);
    }

    public function test_admin_can_delete_all_leave_entitlements_for_a_staff_member(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();
        $year = (int) now()->year;

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => $year,
            'entitlement' => 14,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.leaves.destroy', $staff), ['year' => $year])
            ->assertRedirect(route('admin.leaves.index', ['year' => $year]));

        $this->assertDatabaseMissing('leave_balances', [
            'user_id' => $staff->id,
        ]);
    }

    public function test_hr_cannot_update_leave_entitlements(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();

        $this->actingAs($hr)
            ->put(route('admin.leaves.update', $staff), [
                'year' => (int) now()->year,
                'entitlements' => [$annual->id => 10],
            ])
            ->assertForbidden();
    }

    public function test_dashboard_shows_leave_matrix_for_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Leave Entitlements')
            ->assertSee('Annual Leave');
    }

    public function test_dashboard_shows_used_and_left_balance_for_each_staff(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create(['name' => 'Balanced Staff']);
        $staff->assignRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => now()->year,
            'entitlement' => 14,
            'used' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Balanced Staff')
            ->assertSee(__('Total entitlement'))
            ->assertSee(__('Total used'))
            ->assertSee(__('Total left'))
            ->assertSee('11') // 14 - 3 left for annual
            ->assertSee(__('left'))
            ->assertSee(__('used'));
    }

    public function test_admin_can_generate_next_year_entitlements(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();
        $medical = LeaveType::where('slug', 'medical')->firstOrFail();
        $year = 2026;

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => $year,
            'entitlement' => 14,
            'used' => 12,
        ]);

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $medical->id,
            'year' => $year,
            'entitlement' => 14,
            'used' => 14,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.leaves.generate'), ['year' => $year])
            ->assertRedirect(route('admin.leaves.index', ['year' => $year + 1]));

        $annualNext = LeaveBalance::where('user_id', $staff->id)
            ->where('leave_type_id', $annual->id)
            ->where('year', $year + 1)
            ->firstOrFail();

        $this->assertSame('14.0', $annualNext->entitlement);
        $this->assertSame('2.0', $annualNext->carried_over); // 14 - 12 unused
        $this->assertSame('0.0', $annualNext->used);

        $medicalNext = LeaveBalance::where('user_id', $staff->id)
            ->where('leave_type_id', $medical->id)
            ->where('year', $year + 1)
            ->firstOrFail();

        $this->assertSame('14.0', $medicalNext->entitlement);
        $this->assertSame('0.0', $medicalNext->carried_over);
        $this->assertSame('0.0', $medicalNext->used);
    }

    public function test_generate_caps_annual_carry_over_at_three_days(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();
        $year = 2026;

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => $year,
            'entitlement' => 14,
            'used' => 0,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.leaves.generate'), ['year' => $year]);

        $annualNext = LeaveBalance::where('user_id', $staff->id)
            ->where('leave_type_id', $annual->id)
            ->where('year', $year + 1)
            ->firstOrFail();

        $this->assertSame('3.0', $annualNext->carried_over);
    }

    public function test_generate_preserves_used_days_in_the_target_year(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();
        $year = 2026;

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => $year + 1,
            'entitlement' => 10,
            'used' => 5,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.leaves.generate'), ['year' => $year]);

        $annualNext = LeaveBalance::where('user_id', $staff->id)
            ->where('leave_type_id', $annual->id)
            ->where('year', $year + 1)
            ->firstOrFail();

        $this->assertSame('14.0', $annualNext->entitlement);
        $this->assertSame('5.0', $annualNext->used);
    }

    public function test_hr_cannot_generate_entitlements(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        $this->actingAs($hr)
            ->post(route('admin.leaves.generate'), ['year' => 2026])
            ->assertForbidden();
    }

    public function test_dashboard_leave_year_can_be_selected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create(['name' => 'Year Staff']);
        $staff->assignRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => 2027,
            'entitlement' => 14,
            'used' => 4,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['year' => 2027]))
            ->assertOk()
            ->assertSee('Leave balance used and left for each staff member in 2027.')
            ->assertSee('2027');
    }
}
