<?php

namespace Tests\Feature;

use App\Models\Payslip;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Support\PayslipSync;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPayslipTest extends TestCase
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

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * Save a salary adjustment and sync its payslip, the same way the admin's
     * Salary & Payslips section does.
     */
    protected function adjustment(User $staff, int $year, int $month, float $rate = 10, float $hours = 8, int $days = 20): SalaryAdjustment
    {
        $adjustment = SalaryAdjustment::create([
            'user_id' => $staff->id,
            'year' => $year,
            'month' => $month,
            'hourly_rate' => $rate,
            'hours_of_work' => $hours,
            'days_of_work' => $days,
        ]);

        PayslipSync::fromAdjustment($adjustment);

        return $adjustment;
    }

    public function test_staff_sees_the_payslips_saved_for_them_on_the_admin_section(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF050']);

        $this->adjustment($staff, 2026, 1, 10, 8, 20);

        $this->actingAs($staff)
            ->get(route('staff.payslips.index'))
            ->assertOk()
            ->assertSee('My Payslips')
            ->assertSee('January 2026')
            ->assertSee('RM 1,600.00');
    }

    public function test_staff_sees_only_their_own_payslips(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF050']);
        $other = $this->staff(['employee_id' => 'STAFF051']);

        $this->adjustment($staff, 2026, 1, 10, 8, 20);
        $this->adjustment($other, 2025, 11, 99, 8, 20);

        $this->actingAs($staff)
            ->get(route('staff.payslips.index'))
            ->assertOk()
            ->assertSee('January 2026')
            ->assertDontSee('November 2025')
            ->assertDontSee('15,840.00');
    }

    public function test_payslip_page_shows_an_empty_state(): void
    {
        $this->actingAs($this->staff())
            ->get(route('staff.payslips.index'))
            ->assertOk()
            ->assertSee('No payslips yet');
    }

    public function test_payslips_without_a_saved_adjustment_are_not_shown(): void
    {
        $staff = $this->staff();

        $legacy = Payslip::create([
            'user_id' => $staff->id,
            'year' => 2026,
            'month' => 6,
            'hourly_rate' => 12,
            'hours_of_work' => 8,
            'days_of_work' => 20,
        ]);
        $legacy->recalculate();
        $legacy->save();

        $this->actingAs($staff)
            ->get(route('staff.payslips.index'))
            ->assertOk()
            ->assertSee('No payslips yet')
            ->assertDontSee('June 2026');
    }

    public function test_an_admin_save_shows_up_in_the_staff_portal(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF056']);

        $this->actingAs($this->admin())
            ->patch(route('admin.staff.salary', $staff), [
                'year' => 2026,
                'month' => 5,
                'hourly_rate' => 17.5,
                'hours_of_work' => 8,
                'days_of_work' => 22,
            ])
            ->assertRedirect();

        $this->actingAs($staff)
            ->get(route('staff.payslips.index'))
            ->assertOk()
            ->assertSee('May 2026')
            ->assertSee('RM 3,080.00');
    }

    public function test_staff_can_download_their_own_payslip_pdf(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF052']);

        $payslip = PayslipSync::fromAdjustment($this->adjustment($staff, 2026, 3, 15, 8, 20));

        $this->actingAs($staff)
            ->get(route('staff.payslips.pdf', ['payslip' => $payslip]))
            ->assertOk()
            ->assertDownload('payslip-STAFF052-2026-03.pdf');
    }

    public function test_downloading_refreshes_the_payslip_from_the_admins_latest_save(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF057']);

        $payslip = Payslip::create([
            'user_id' => $staff->id,
            'year' => 2026,
            'month' => 4,
            'hourly_rate' => 1,
            'hours_of_work' => 1,
            'days_of_work' => 1,
        ]);
        $payslip->recalculate();
        $payslip->save();

        $this->adjustment($staff, 2026, 4, 20, 8, 20);

        $this->actingAs($staff)
            ->get(route('staff.payslips.pdf', ['payslip' => $payslip]))
            ->assertOk()
            ->assertDownload('payslip-STAFF057-2026-04.pdf');

        $payslip->refresh();
        $this->assertEqualsWithDelta(20.0, (float) $payslip->hourly_rate, 0.01);
        $this->assertEqualsWithDelta(3200.0, (float) $payslip->basic, 0.01);
        $this->assertEqualsWithDelta(3200.0, (float) $payslip->net, 0.01);
    }

    public function test_staff_cannot_download_another_staff_payslip(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF053']);
        $other = $this->staff(['employee_id' => 'STAFF054']);

        $foreign = PayslipSync::fromAdjustment($this->adjustment($other, 2026, 4));

        $this->actingAs($staff)
            ->get(route('staff.payslips.pdf', ['payslip' => $foreign]))
            ->assertNotFound();
    }

    public function test_staff_cannot_download_a_period_without_a_saved_adjustment(): void
    {
        $staff = $this->staff();

        $legacy = Payslip::create([
            'user_id' => $staff->id,
            'year' => 2026,
            'month' => 7,
            'hourly_rate' => 12,
            'hours_of_work' => 8,
            'days_of_work' => 20,
        ]);
        $legacy->recalculate();
        $legacy->save();

        $this->actingAs($staff)
            ->get(route('staff.payslips.pdf', ['payslip' => $legacy]))
            ->assertNotFound();
    }

    public function test_staff_can_delete_their_own_payslip(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF058']);

        $payslip = PayslipSync::fromAdjustment($this->adjustment($staff, 2026, 8));

        $this->actingAs($staff)
            ->delete(route('staff.payslips.destroy', ['payslip' => $payslip]))
            ->assertRedirect(route('staff.payslips.index'))
            ->assertSessionHas('status', 'payslip-removed');

        $this->assertDatabaseMissing('payslips', ['id' => $payslip->id]);
    }

    public function test_a_removed_payslip_stays_removed_until_hr_saves_again(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF059']);

        $this->adjustment($staff, 2026, 9, 10, 8, 20);
        $payslip = $staff->payslips()->firstOrFail();

        $this->actingAs($staff)
            ->delete(route('staff.payslips.destroy', ['payslip' => $payslip]));

        $this->assertDatabaseCount('payslips', 0);

        $this->actingAs($staff)
            ->get(route('staff.payslips.index'))
            ->assertOk()
            ->assertSee('No payslips yet')
            ->assertDontSee('September 2026');

        $this->assertDatabaseCount('payslips', 0);

        $this->actingAs($this->admin())
            ->patch(route('admin.staff.salary', $staff), [
                'year' => 2026,
                'month' => 9,
                'hourly_rate' => 10,
                'hours_of_work' => 8,
                'days_of_work' => 20,
            ]);

        $this->actingAs($staff)
            ->get(route('staff.payslips.index'))
            ->assertOk()
            ->assertSee('September 2026')
            ->assertSee('RM 1,600.00');

        $this->assertDatabaseCount('payslips', 1);
    }

    public function test_staff_cannot_delete_another_staff_payslip(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF060']);
        $other = $this->staff(['employee_id' => 'STAFF061']);

        $foreign = PayslipSync::fromAdjustment($this->adjustment($other, 2026, 10));

        $this->actingAs($staff)
            ->delete(route('staff.payslips.destroy', ['payslip' => $foreign]))
            ->assertNotFound();

        $this->assertDatabaseHas('payslips', ['id' => $foreign->id]);
    }

    public function test_admin_cannot_delete_a_staff_payslip(): void
    {
        $staff = $this->staff();

        $payslip = PayslipSync::fromAdjustment($this->adjustment($staff, 2026, 11));

        $this->actingAs($this->admin())
            ->delete(route('staff.payslips.destroy', ['payslip' => $payslip]))
            ->assertForbidden();

        $this->assertDatabaseHas('payslips', ['id' => $payslip->id]);
    }

    public function test_staff_dashboard_and_navigation_link_to_payslips(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF055']);

        $this->adjustment($staff, 2026, 2);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('My Payslips')
            ->assertSee(route('staff.payslips.index'));
    }

    public function test_admin_cannot_open_the_staff_payslip_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('staff.payslips.index'))
            ->assertForbidden();
    }
}
