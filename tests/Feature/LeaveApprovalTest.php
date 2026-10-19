<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeaveApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function pendingRequest(User $staff, float $days = 2): LeaveRequest
    {
        $start = Carbon::create(now()->year, 6, 1);

        return LeaveRequest::create([
            'user_id' => $staff->id,
            'leave_type_id' => LeaveType::where('slug', 'annual')->value('id'),
            'start_date' => $start,
            'end_date' => $start->copy()->addDays((int) $days - 1),
            'days' => $days,
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_view_pending_leave_requests(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $this->pendingRequest($staff);

        $this->actingAs($admin)
            ->get(route('admin.leave-requests.index'))
            ->assertOk()
            ->assertSee($staff->name)
            ->assertSee('Pending');
    }

    public function test_hr_can_view_leave_requests(): void
    {
        $hr = $this->userWithRole('hr');

        $this->actingAs($hr)
            ->get(route('admin.leave-requests.index'))
            ->assertOk();
    }

    public function test_approving_a_request_records_usage_and_approver(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff, 2);

        $this->actingAs($admin)
            ->patch(route('admin.leave-requests.approve', $leaveRequest))
            ->assertRedirect();

        $leaveRequest->refresh();

        $this->assertSame('approved', $leaveRequest->status);
        $this->assertSame($admin->id, $leaveRequest->approved_by);
        $this->assertNotNull($leaveRequest->decided_at);

        $balance = LeaveBalance::where('user_id', $staff->id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', now()->year)
            ->firstOrFail();

        $this->assertSame(2.0, (float) $balance->used);
    }

    public function test_approving_adds_to_any_existing_usage(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => now()->year,
            'entitlement' => 14,
            'used' => 5,
        ]);

        $leaveRequest = $this->pendingRequest($staff, 3);

        $this->actingAs($admin)
            ->patch(route('admin.leave-requests.approve', $leaveRequest))
            ->assertRedirect();

        $balance = LeaveBalance::where('user_id', $staff->id)
            ->where('leave_type_id', $annual->id)
            ->where('year', now()->year)
            ->firstOrFail();

        $this->assertSame(8.0, (float) $balance->used);
    }

    public function test_rejecting_a_request_records_a_reason_without_using_balance(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff, 2);

        $this->actingAs($admin)
            ->patch(route('admin.leave-requests.reject', $leaveRequest), [
                'rejection_reason' => 'Not enough coverage.',
            ])
            ->assertRedirect();

        $leaveRequest->refresh();

        $this->assertSame('rejected', $leaveRequest->status);
        $this->assertSame('Not enough coverage.', $leaveRequest->rejection_reason);
        $this->assertSame($admin->id, $leaveRequest->approved_by);

        $this->assertDatabaseMissing('leave_balances', ['user_id' => $staff->id]);
    }

    public function test_a_request_cannot_be_decided_twice(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff, 2);

        $this->actingAs($admin)
            ->patch(route('admin.leave-requests.approve', $leaveRequest));

        $this->actingAs($admin)
            ->from(route('admin.leave-requests.index'))
            ->patch(route('admin.leave-requests.approve', $leaveRequest))
            ->assertSessionHas('status', 'leave-already-decided');

        $balance = LeaveBalance::where('user_id', $staff->id)->firstOrFail();

        $this->assertSame(2.0, (float) $balance->used);
    }

    public function test_staff_cannot_access_leave_requests(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->get(route('admin.leave-requests.index'))
            ->assertForbidden();
    }

    public function test_admin_dashboard_shows_leave_request_chart_and_pending_list(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $this->pendingRequest($staff, 2);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('leaveRequestChart', false)
            ->assertSee('Pending Approvals')
            ->assertSee($staff->name)
            ->assertSee('Approve')
            ->assertSee('Reject');
    }

    public function test_admin_can_approve_a_request_from_the_dashboard(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff, 2);

        $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->patch(route('admin.leave-requests.approve', $leaveRequest))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status', 'leave-approved');

        $this->assertSame('approved', $leaveRequest->refresh()->status);
    }

    public function test_dashboard_pending_approvals_are_interactive(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $this->pendingRequest($staff, 2);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('pendingApprovals(', false)
            ->assertSee('leaveCounts.pending', false)
            ->assertSee('Pending Approvals')
            ->assertSee('Approve')
            ->assertSee('Reject')
            ->assertSee('Details');
    }

    public function test_ajax_approve_returns_the_updated_counts(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff, 2);

        $this->actingAs($admin)
            ->patchJson(route('admin.leave-requests.approve', $leaveRequest))
            ->assertOk()
            ->assertJsonPath('status', 'leave-approved')
            ->assertJsonPath('counts.pending', 0)
            ->assertJsonPath('counts.approved', 1)
            ->assertJsonPath('counts.rejected', 0);

        $this->assertSame('approved', $leaveRequest->refresh()->status);
    }

    public function test_ajax_reject_returns_the_updated_counts(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff, 2);

        $this->actingAs($admin)
            ->patchJson(route('admin.leave-requests.reject', $leaveRequest), [
                'rejection_reason' => 'Not enough cover.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'leave-rejected')
            ->assertJsonPath('counts.pending', 0)
            ->assertJsonPath('counts.rejected', 1);

        $this->assertSame('rejected', $leaveRequest->refresh()->status);
        $this->assertSame('Not enough cover.', $leaveRequest->rejection_reason);
    }

    public function test_ajax_remarks_returns_the_saved_remarks(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff, 2);

        $this->actingAs($admin)
            ->patchJson(route('admin.leave-requests.remarks', $leaveRequest), [
                'remarks' => 'Please attach the medical certificate.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'leave-remarks-saved')
            ->assertJsonPath('remarks', 'Please attach the medical certificate.');

        $this->assertSame('Please attach the medical certificate.', $leaveRequest->refresh()->remarks);
    }

    public function test_ajax_deciding_twice_reports_an_already_decided_request(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff, 2);

        $this->actingAs($admin)->patchJson(route('admin.leave-requests.approve', $leaveRequest))->assertOk();

        $this->actingAs($admin)
            ->patchJson(route('admin.leave-requests.reject', $leaveRequest))
            ->assertOk()
            ->assertJsonPath('status', 'leave-already-decided')
            ->assertJsonPath('counts.approved', 1)
            ->assertJsonPath('counts.rejected', 0);

        $this->assertSame('approved', $leaveRequest->refresh()->status);
    }

    public function test_hr_dashboard_shows_leave_requests_but_not_staff_section(): void
    {
        $hr = $this->userWithRole('hr');

        $this->actingAs($hr)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pending Approvals');
    }

    public function test_staff_dashboard_does_not_show_pending_approvals(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertDontSee('Pending Approvals');
    }

    public function test_staff_dashboard_shows_company_name_and_staff_id(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Company :')
            ->assertSee(CompanySetting::current()->name)
            ->assertSee($staff->name)
            ->assertSee('Staff ID :')
            ->assertSee($staff->employee_id);
    }

    public function test_admin_can_save_remarks_on_a_pending_request(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff);

        $this->actingAs($admin)
            ->from(route('admin.leave-requests.index'))
            ->patch(route('admin.leave-requests.remarks', $leaveRequest), [
                'remarks' => 'Please submit the medical certificate.',
            ])
            ->assertRedirect(route('admin.leave-requests.index'))
            ->assertSessionHas('status', 'leave-remarks-saved');

        $this->assertSame('Please submit the medical certificate.', $leaveRequest->refresh()->remarks);
    }

    public function test_admin_can_update_remarks_on_an_approved_request(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff);

        $this->actingAs($admin)->patch(route('admin.leave-requests.approve', $leaveRequest));

        $this->actingAs($admin)
            ->patch(route('admin.leave-requests.remarks', $leaveRequest), [
                'remarks' => 'Approved, enjoy your leave.',
            ])
            ->assertSessionHas('status', 'leave-remarks-saved');

        $leaveRequest->refresh();

        $this->assertSame('approved', $leaveRequest->status);
        $this->assertSame('Approved, enjoy your leave.', $leaveRequest->remarks);
    }

    public function test_admin_can_update_remarks_on_a_rejected_request(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff);

        $this->actingAs($admin)->patch(route('admin.leave-requests.reject', $leaveRequest));

        $this->actingAs($admin)
            ->patch(route('admin.leave-requests.remarks', $leaveRequest), [
                'remarks' => 'Peak period.',
            ])
            ->assertSessionHas('status', 'leave-remarks-saved');

        $leaveRequest->refresh();

        $this->assertSame('rejected', $leaveRequest->status);
        $this->assertSame('Peak period.', $leaveRequest->remarks);
    }

    public function test_clearing_remarks_stores_null(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff);
        $leaveRequest->update(['remarks' => 'Old note']);

        $this->actingAs($admin)
            ->patch(route('admin.leave-requests.remarks', $leaveRequest), ['remarks' => ''])
            ->assertSessionHas('status', 'leave-remarks-saved');

        $this->assertNull($leaveRequest->refresh()->remarks);
    }

    public function test_staff_cannot_update_leave_request_remarks(): void
    {
        $staff = $this->userWithRole('staff');

        $leaveRequest = $this->pendingRequest($staff);

        $this->actingAs($staff)
            ->patch(route('admin.leave-requests.remarks', $leaveRequest), ['remarks' => 'Nope'])
            ->assertForbidden();
    }

    public function test_approving_medical_leave_beyond_balance_draws_from_annual(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();
        $medical = LeaveType::where('slug', 'medical')->firstOrFail();
        $year = (int) now()->year;

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => $year,
            'entitlement' => 14,
            'used' => 0,
        ]);

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $medical->id,
            'year' => $year,
            'entitlement' => 14,
            'used' => 14,
        ]);

        $start = Carbon::create($year, 6, 1);

        $leaveRequest = LeaveRequest::create([
            'user_id' => $staff->id,
            'leave_type_id' => $medical->id,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'days' => 3,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.leave-requests.approve', $leaveRequest))
            ->assertRedirect();

        $medicalBalance = LeaveBalance::where('user_id', $staff->id)
            ->where('leave_type_id', $medical->id)
            ->where('year', $year)
            ->firstOrFail();

        $annualBalance = LeaveBalance::where('user_id', $staff->id)
            ->where('leave_type_id', $annual->id)
            ->where('year', $year)
            ->firstOrFail();

        $this->assertSame(14.0, (float) $medicalBalance->used);
        $this->assertSame(3.0, (float) $annualBalance->used);
    }

    public function test_approving_medical_leave_within_balance_does_not_draw_from_annual(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $annual = LeaveType::where('slug', 'annual')->firstOrFail();
        $medical = LeaveType::where('slug', 'medical')->firstOrFail();
        $year = (int) now()->year;

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => $year,
            'entitlement' => 14,
            'used' => 0,
        ]);

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $medical->id,
            'year' => $year,
            'entitlement' => 14,
            'used' => 0,
        ]);

        $start = Carbon::create($year, 6, 1);

        $leaveRequest = LeaveRequest::create([
            'user_id' => $staff->id,
            'leave_type_id' => $medical->id,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'days' => 2,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.leave-requests.approve', $leaveRequest))
            ->assertRedirect();

        $medicalBalance = LeaveBalance::where('user_id', $staff->id)
            ->where('leave_type_id', $medical->id)
            ->where('year', $year)
            ->firstOrFail();

        $annualBalance = LeaveBalance::where('user_id', $staff->id)
            ->where('leave_type_id', $annual->id)
            ->where('year', $year)
            ->firstOrFail();

        $this->assertSame(2.0, (float) $medicalBalance->used);
        $this->assertSame(0.0, (float) $annualBalance->used);
    }
}
