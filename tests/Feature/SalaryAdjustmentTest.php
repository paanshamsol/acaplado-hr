<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Payslip;
use App\Models\SalaryAdjustment;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    protected function hr(): User
    {
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        return $hr;
    }

    protected function staff(array $attributes = []): User
    {
        $staff = User::factory()->create($attributes);
        $staff->assignRole('staff');

        return $staff;
    }

    public function test_staff_edit_page_shows_the_salary_adjustment_card(): void
    {
        $staff = $this->staff([
            'hourly_rate' => 12.5,
            'hours_of_work' => 8,
            'days_of_work' => 20,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.staff.edit', $staff))
            ->assertOk()
            ->assertSee('Salary Adjustment')
            ->assertSee('Save Salary Adjustment')
            ->assertSee('No salary adjustments saved yet.');
    }

    public function test_staff_cannot_view_the_staff_edit_page(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->get(route('admin.staff.edit', $staff))
            ->assertForbidden();
    }

    public function test_saving_a_salary_adjustment_records_the_payroll_for_the_period(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF010']);

        $this->actingAs($this->admin())
            ->patch(route('admin.staff.salary', $staff), [
                'year' => 2026,
                'month' => 3,
                'hourly_rate' => 20,
                'hours_of_work' => 8,
                'days_of_work' => 22,
            ])
            ->assertRedirect(route('admin.staff.edit', ['staff' => $staff, 'year' => 2026, 'month' => 3]))
            ->assertSessionHas('status', 'salary-updated');

        $this->assertDatabaseHas('salary_adjustments', [
            'user_id' => $staff->id,
            'year' => 2026,
            'month' => 3,
            'hourly_rate' => 20,
            'hours_of_work' => 8,
            'days_of_work' => 22,
        ]);

        $staff->refresh();
        $this->assertEqualsWithDelta(20.0, (float) $staff->hourly_rate, 0.01);
        $this->assertEqualsWithDelta(8.0, (float) $staff->hours_of_work, 0.01);
        $this->assertSame(22, (int) $staff->days_of_work);

        $payslip = Payslip::where('user_id', $staff->id)->where('year', 2026)->where('month', 3)->firstOrFail();

        $basic = round(20 * 8 * 22, 2);

        $this->assertEqualsWithDelta($basic, (float) $payslip->basic, 0.01);
        $this->assertEqualsWithDelta($basic, (float) $payslip->net, 0.01);
        $this->assertNotNull($payslip->generated_by);
    }

    public function test_saving_a_salary_adjustment_refreshes_an_existing_record_and_keeps_allowances(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF011']);

        $payslip = Payslip::create([
            'user_id' => $staff->id,
            'year' => 2026,
            'month' => 4,
            'hourly_rate' => 10,
            'hours_of_work' => 8,
            'days_of_work' => 20,
            'allowances' => 200,
            'other_deductions' => 50,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.staff.salary', $staff), [
                'year' => 2026,
                'month' => 4,
                'hourly_rate' => 25,
                'hours_of_work' => 8,
                'days_of_work' => 22,
            ])
            ->assertRedirect();

        $this->assertSame(1, Payslip::where('user_id', $staff->id)->where('year', 2026)->where('month', 4)->count());

        $payslip->refresh();

        $basic = round(25 * 8 * 22, 2);

        $this->assertEqualsWithDelta($basic, (float) $payslip->basic, 0.01);
        $this->assertEqualsWithDelta(200.0, (float) $payslip->allowances, 0.01);
        $this->assertEqualsWithDelta(50.0, (float) $payslip->other_deductions, 0.01);
        $this->assertEqualsWithDelta($basic + 200 - 50, (float) $payslip->net, 0.01);
    }

    public function test_saving_again_does_not_duplicate_the_payslip(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF012']);

        $payload = [
            'year' => 2026,
            'month' => 5,
            'hourly_rate' => 15,
            'hours_of_work' => 8,
            'days_of_work' => 20,
        ];

        $this->actingAs($this->admin())->patch(route('admin.staff.salary', $staff), $payload);
        $this->actingAs($this->admin())->patch(route('admin.staff.salary', $staff), array_merge($payload, ['days_of_work' => 21]));

        $payslips = Payslip::where('user_id', $staff->id)->where('year', 2026)->where('month', 5)->get();

        $this->assertCount(1, $payslips);
        $this->assertSame(21, (int) $payslips->first()->days_of_work);
        $this->assertEqualsWithDelta(round(15 * 8 * 21, 2), (float) $payslips->first()->basic, 0.01);
    }

    public function test_salary_history_is_listed_and_the_selected_period_is_loaded(): void
    {
        $staff = $this->staff();

        SalaryAdjustment::create([
            'user_id' => $staff->id,
            'year' => 2026,
            'month' => 1,
            'hourly_rate' => 15.5,
            'hours_of_work' => 7,
            'days_of_work' => 10,
        ]);

        SalaryAdjustment::create([
            'user_id' => $staff->id,
            'year' => 2026,
            'month' => 2,
            'hourly_rate' => 30,
            'hours_of_work' => 8,
            'days_of_work' => 20,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.staff.edit', ['staff' => $staff, 'year' => 2026, 'month' => 1]))
            ->assertOk()
            ->assertSee('January 2026')
            ->assertSee('February 2026')
            ->assertSee('RM 1,085.00')
            ->assertSee('value="15.5"', false);

        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, 'In form'));
        $this->assertSame(1, substr_count($content, __('Load')));
    }

    public function test_salary_adjustment_validation_rejects_bad_values(): void
    {
        $staff = $this->staff();

        $this->actingAs($this->admin())
            ->patch(route('admin.staff.salary', $staff), [
                'year' => 2026,
                'month' => 13,
                'hourly_rate' => -1,
                'hours_of_work' => 'abc',
                'days_of_work' => 400,
            ])
            ->assertSessionHasErrors(['month', 'hourly_rate', 'hours_of_work', 'days_of_work']);

        $this->assertDatabaseCount('payslips', 0);
        $this->assertDatabaseCount('salary_adjustments', 0);
    }

    public function test_staff_cannot_save_a_salary_adjustment(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->patch(route('admin.staff.salary', $staff), [
                'year' => 2026,
                'month' => 3,
                'hourly_rate' => 20,
                'hours_of_work' => 8,
                'days_of_work' => 22,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('salary_adjustments', 0);
        $this->assertDatabaseCount('payslips', 0);
    }

    public function test_hr_can_save_a_salary_adjustment(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF014']);

        $this->actingAs($this->hr())
            ->patch(route('admin.staff.salary', $staff), [
                'year' => 2026,
                'month' => 7,
                'hourly_rate' => 18,
                'hours_of_work' => 8,
                'days_of_work' => 20,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('salary_adjustments', [
            'user_id' => $staff->id,
            'year' => 2026,
            'month' => 7,
        ]);
    }

    public function test_admin_salary_and_payslip_management_pages_are_gone(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/salary')->assertNotFound();
        $this->actingAs($admin)->get('/admin/payslips')->assertNotFound();
        $this->actingAs($admin)->get('/admin/payslips/1/edit')->assertNotFound();
    }

    public function test_admin_navigation_links_to_the_payslip_section(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Payslips')
            ->assertSee(route('admin.dashboard').'#salary-payslips', false);
    }

    public function test_hr_navigation_links_to_the_payslip_section(): void
    {
        $this->actingAs($this->hr())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.dashboard').'#salary-payslips', false);
    }

    public function test_admin_dashboard_shows_the_salary_and_payslip_section(): void
    {
        $admin = $this->admin();
        $alice = $this->staff(['employee_id' => 'STAFF020', 'name' => 'Alice Salary']);
        $bob = $this->staff(['employee_id' => 'STAFF021', 'name' => 'Bob Salary']);

        SalaryAdjustment::create([
            'user_id' => $alice->id,
            'year' => 2026,
            'month' => 3,
            'hourly_rate' => 10,
            'hours_of_work' => 8,
            'days_of_work' => 20,
        ]);

        SalaryAdjustment::create([
            'user_id' => $bob->id,
            'year' => 2026,
            'month' => 3,
            'hourly_rate' => 20,
            'hours_of_work' => 8,
            'days_of_work' => 22,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['salary_year' => 2026, 'salary_month' => 3]))
            ->assertOk()
            ->assertSee('Salary & Payslips')
            ->assertSee('Alice Salary')
            ->assertSee('Bob Salary')
            ->assertSee('1,600.00')
            ->assertSee('3,520.00')
            ->assertSee('RM 5,120.00')
            ->assertSee('value="10"', false)
            ->assertSee('value="22"', false)
            ->assertSee(route('admin.staff.payslip.pdf', ['staff' => $alice, 'year' => 2026, 'month' => 3]));
    }

    public function test_saving_from_the_dashboard_generates_the_payslip_and_returns_to_the_period(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF022']);

        $this->actingAs($this->admin())
            ->patch(route('admin.staff.salary', $staff), [
                'year' => 2026,
                'month' => 9,
                'hourly_rate' => 12,
                'hours_of_work' => 8,
                'days_of_work' => 21,
                'context' => 'dashboard',
            ])
            ->assertRedirect(route('admin.dashboard', ['salary_year' => 2026, 'salary_month' => 9]))
            ->assertSessionHas('status', 'salary-updated');

        $payslip = Payslip::where('user_id', $staff->id)->where('year', 2026)->where('month', 9)->firstOrFail();
        $basic = round(12 * 8 * 21, 2);

        $this->assertEqualsWithDelta($basic, (float) $payslip->basic, 0.01);
        $this->assertEqualsWithDelta($basic, (float) $payslip->net, 0.01);
    }

    public function test_dashboard_save_keeps_the_leave_year_filter(): void
    {
        $staff = $this->staff();

        $this->actingAs($this->admin())
            ->patch(route('admin.staff.salary', $staff), [
                'year' => 2026,
                'month' => 9,
                'hourly_rate' => 12,
                'hours_of_work' => 8,
                'days_of_work' => 21,
                'context' => 'dashboard',
                'return_year' => 2025,
            ])
            ->assertRedirect(route('admin.dashboard', [
                'salary_year' => 2026,
                'salary_month' => 9,
                'year' => 2025,
            ]));
    }

    public function test_admin_can_download_a_payslip_pdf_that_syncs_from_the_adjustment(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF023']);

        SalaryAdjustment::create([
            'user_id' => $staff->id,
            'year' => 2026,
            'month' => 8,
            'hourly_rate' => 15,
            'hours_of_work' => 8,
            'days_of_work' => 20,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.staff.payslip.pdf', ['staff' => $staff, 'year' => 2026, 'month' => 8]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $response->assertDownload('payslip-STAFF023-2026-08.pdf');

        $payslip = Payslip::where('user_id', $staff->id)->where('year', 2026)->where('month', 8)->firstOrFail();

        $this->assertEqualsWithDelta(2400.0, (float) $payslip->basic, 0.01);
        $this->assertEqualsWithDelta(2400.0, (float) $payslip->net, 0.01);
    }

    public function test_payslip_pdf_returns_not_found_without_a_saved_adjustment(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.staff.payslip.pdf', ['staff' => $this->staff(), 'year' => 2026, 'month' => 1]))
            ->assertNotFound();
    }

    public function test_staff_cannot_download_an_admin_payslip(): void
    {
        $staff = $this->staff();

        SalaryAdjustment::create([
            'user_id' => $staff->id,
            'year' => 2026,
            'month' => 8,
            'hourly_rate' => 15,
            'hours_of_work' => 8,
            'days_of_work' => 20,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.staff.payslip.pdf', ['staff' => $staff, 'year' => 2026, 'month' => 8]))
            ->assertForbidden();
    }

    public function test_payslip_pdf_includes_the_company_information_and_salary_details(): void
    {
        $staff = $this->staff(['employee_id' => 'STAFF024', 'name' => 'Payslip Target']);

        CompanySetting::current()->update([
            'name' => 'Acme Payroll Sdn Bhd',
            'address' => '1 Jalan Test, Kuala Lumpur',
            'phone' => '0123456789',
            'email' => 'payroll@acme.test',
        ]);

        $payslip = Payslip::create([
            'user_id' => $staff->id,
            'year' => 2026,
            'month' => 8,
            'hourly_rate' => 15,
            'hours_of_work' => 8,
            'days_of_work' => 20,
        ]);
        $payslip->recalculate();

        $this->view('pdf.payslip', [
            'payslip' => $payslip->load('user', 'generator'),
            'company' => CompanySetting::current(),
        ])
            ->assertSee('Acme Payroll Sdn Bhd')
            ->assertSee('1 Jalan Test, Kuala Lumpur')
            ->assertSee('0123456789')
            ->assertSee('payroll@acme.test')
            ->assertSee('Payslip Target')
            ->assertSee('STAFF024')
            ->assertSee('August 2026')
            ->assertSee('2,400.00');
    }
}
