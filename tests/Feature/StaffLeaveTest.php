<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffLeaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
    }

    protected function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        return $user;
    }

    public function test_staff_can_view_their_leave_page(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->get(route('staff.leaves.index'))
            ->assertOk()
            ->assertSee('My Leave')
            ->assertSee('Annual Leave')
            ->assertSee('Remaining');
    }

    public function test_staff_can_apply_for_leave(): void
    {
        $staff = $this->staff();
        $annual = LeaveType::where('slug', 'annual')->firstOrFail();

        $start = Carbon::create(now()->year, 6, 1);
        $end = Carbon::create(now()->year, 6, 3);

        $this->actingAs($staff)
            ->post(route('staff.leaves.store'), [
                'leave_type_id' => $annual->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'reason' => 'Family trip',
            ])
            ->assertRedirect(route('staff.leaves.index'));

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'status' => 'pending',
            'days' => 3,
        ]);
    }

    public function test_staff_cannot_apply_beyond_the_remaining_balance(): void
    {
        $staff = $this->staff();
        $annual = LeaveType::where('slug', 'annual')->firstOrFail();

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => now()->year,
            'entitlement' => 1,
        ]);

        $this->actingAs($staff)
            ->from(route('staff.leaves.index'))
            ->post(route('staff.leaves.store'), [
                'leave_type_id' => $annual->id,
                'start_date' => Carbon::create(now()->year, 6, 1)->toDateString(),
                'end_date' => Carbon::create(now()->year, 6, 5)->toDateString(),
            ])
            ->assertSessionHasErrors('start_date');

        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_staff_cannot_apply_for_leave_that_overlaps_an_existing_request(): void
    {
        $staff = $this->staff();
        $annual = LeaveType::where('slug', 'annual')->firstOrFail();

        LeaveRequest::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'start_date' => Carbon::create(now()->year, 6, 1),
            'end_date' => Carbon::create(now()->year, 6, 5),
            'days' => 5,
            'status' => 'pending',
        ]);

        $this->actingAs($staff)
            ->from(route('staff.leaves.index'))
            ->post(route('staff.leaves.store'), [
                'leave_type_id' => $annual->id,
                'start_date' => Carbon::create(now()->year, 6, 4)->toDateString(),
                'end_date' => Carbon::create(now()->year, 6, 8)->toDateString(),
            ])
            ->assertSessionHasErrors('start_date');

        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_staff_cannot_apply_for_leave_that_overlaps_an_approved_request(): void
    {
        $staff = $this->staff();
        $annual = LeaveType::where('slug', 'annual')->firstOrFail();

        LeaveRequest::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'start_date' => Carbon::create(now()->year, 6, 1),
            'end_date' => Carbon::create(now()->year, 6, 5),
            'days' => 5,
            'status' => 'approved',
        ]);

        $this->actingAs($staff)
            ->from(route('staff.leaves.index'))
            ->post(route('staff.leaves.store'), [
                'leave_type_id' => $annual->id,
                'start_date' => Carbon::create(now()->year, 6, 5)->toDateString(),
                'end_date' => Carbon::create(now()->year, 6, 6)->toDateString(),
            ])
            ->assertSessionHasErrors('start_date');

        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_staff_can_apply_again_after_a_rejected_request(): void
    {
        $staff = $this->staff();
        $annual = LeaveType::where('slug', 'annual')->firstOrFail();

        LeaveRequest::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'start_date' => Carbon::create(now()->year, 6, 1),
            'end_date' => Carbon::create(now()->year, 6, 5),
            'days' => 5,
            'status' => 'rejected',
        ]);

        $this->actingAs($staff)
            ->post(route('staff.leaves.store'), [
                'leave_type_id' => $annual->id,
                'start_date' => Carbon::create(now()->year, 6, 1)->toDateString(),
                'end_date' => Carbon::create(now()->year, 6, 5)->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff.leaves.index'));

        $this->assertDatabaseCount('leave_requests', 2);
    }

    public function test_medical_leave_requires_an_attachment(): void
    {
        $staff = $this->staff();
        $medical = LeaveType::where('slug', 'medical')->firstOrFail();

        $this->actingAs($staff)
            ->from(route('staff.leaves.index'))
            ->post(route('staff.leaves.store'), [
                'leave_type_id' => $medical->id,
                'start_date' => Carbon::create(now()->year, 6, 1)->toDateString(),
                'end_date' => Carbon::create(now()->year, 6, 1)->toDateString(),
            ])
            ->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_annual_emergency_and_bereavement_leave_do_not_require_an_attachment(): void
    {
        $staff = $this->staff();

        foreach (['annual', 'emergency', 'bereavement'] as $slug) {
            $type = LeaveType::where('slug', $slug)->firstOrFail();

            $this->actingAs($staff)
                ->post(route('staff.leaves.store'), [
                    'leave_type_id' => $type->id,
                    'start_date' => Carbon::create(now()->year, 6, 1)->toDateString(),
                    'end_date' => Carbon::create(now()->year, 6, 1)->toDateString(),
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('staff.leaves.index'));
        }

        $this->assertSame(3, LeaveRequest::count());
    }

    public function test_leave_form_marks_which_types_require_a_document(): void
    {
        $staff = $this->staff();
        $medical = LeaveType::where('slug', 'medical')->firstOrFail();
        $annual = LeaveType::where('slug', 'annual')->firstOrFail();

        $response = $this->actingAs($staff)->get(route('staff.leaves.index'));

        $response->assertOk()
            ->assertSee('data-requires="1"', false)
            ->assertSee('data-requires="0"', false)
            ->assertSee('x-bind:disabled="! needsDoc"', false)
            ->assertSee('A supporting document is only available for Medical Leave');

        $this->assertSame('1', LeaveType::findOrFail($medical->id)->requires_attachment ? '1' : '0');
        $this->assertSame('0', LeaveType::findOrFail($annual->id)->requires_attachment ? '1' : '0');
    }

    public function test_staff_can_apply_with_an_attachment(): void
    {
        Storage::fake('public');

        $staff = $this->staff();
        $medical = LeaveType::where('slug', 'medical')->firstOrFail();

        $this->actingAs($staff)
            ->post(route('staff.leaves.store'), [
                'leave_type_id' => $medical->id,
                'start_date' => Carbon::create(now()->year, 6, 1)->toDateString(),
                'end_date' => Carbon::create(now()->year, 6, 1)->toDateString(),
                'attachment' => UploadedFile::fake()->image('mc.jpg'),
            ])
            ->assertRedirect(route('staff.leaves.index'));

        $request = LeaveRequest::firstOrFail();

        $this->assertNotNull($request->attachment_path);
        Storage::disk('public')->assertExists($request->attachment_path);
    }

    public function test_staff_can_cancel_their_own_pending_request(): void
    {
        $staff = $this->staff();

        $leaveRequest = LeaveRequest::create([
            'user_id' => $staff->id,
            'leave_type_id' => LeaveType::where('slug', 'annual')->value('id'),
            'start_date' => Carbon::create(now()->year, 6, 1),
            'end_date' => Carbon::create(now()->year, 6, 1),
            'days' => 1,
            'status' => 'pending',
        ]);

        $this->actingAs($staff)
            ->delete(route('staff.leaves.destroy', $leaveRequest))
            ->assertRedirect(route('staff.leaves.index'));

        $this->assertDatabaseMissing('leave_requests', ['id' => $leaveRequest->id]);
    }

    public function test_staff_cannot_cancel_another_staff_members_request(): void
    {
        $staff = $this->staff();
        $other = $this->staff();

        $leaveRequest = LeaveRequest::create([
            'user_id' => $other->id,
            'leave_type_id' => LeaveType::where('slug', 'annual')->value('id'),
            'start_date' => Carbon::create(now()->year, 6, 1),
            'end_date' => Carbon::create(now()->year, 6, 1),
            'days' => 1,
            'status' => 'pending',
        ]);

        $this->actingAs($staff)
            ->delete(route('staff.leaves.destroy', $leaveRequest))
            ->assertForbidden();

        $this->assertDatabaseHas('leave_requests', ['id' => $leaveRequest->id]);
    }

    public function test_admin_cannot_access_the_staff_leave_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('staff.leaves.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_the_staff_leave_page(): void
    {
        $this->get(route('staff.leaves.index'))->assertRedirect(route('login'));
    }

    public function test_staff_can_apply_for_emergency_leave_using_annual_backup(): void
    {
        $staff = $this->staff();
        $emergency = LeaveType::where('slug', 'emergency')->firstOrFail();
        $annual = LeaveType::where('slug', 'annual')->firstOrFail();
        $year = (int) now()->year;

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $emergency->id,
            'year' => $year,
            'entitlement' => 3,
            'used' => 3,
        ]);

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => $year,
            'entitlement' => 14,
            'used' => 0,
        ]);

        $this->actingAs($staff)
            ->post(route('staff.leaves.store'), [
                'leave_type_id' => $emergency->id,
                'start_date' => Carbon::create($year, 6, 1)->toDateString(),
                'end_date' => Carbon::create($year, 6, 3)->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff.leaves.index'));

        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_staff_cannot_apply_beyond_type_and_annual_balance(): void
    {
        $staff = $this->staff();
        $emergency = LeaveType::where('slug', 'emergency')->firstOrFail();
        $annual = LeaveType::where('slug', 'annual')->firstOrFail();
        $year = (int) now()->year;

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $emergency->id,
            'year' => $year,
            'entitlement' => 3,
            'used' => 3,
        ]);

        LeaveBalance::create([
            'user_id' => $staff->id,
            'leave_type_id' => $annual->id,
            'year' => $year,
            'entitlement' => 14,
            'used' => 14,
        ]);

        $this->actingAs($staff)
            ->from(route('staff.leaves.index'))
            ->post(route('staff.leaves.store'), [
                'leave_type_id' => $emergency->id,
                'start_date' => Carbon::create($year, 6, 1)->toDateString(),
                'end_date' => Carbon::create($year, 6, 1)->toDateString(),
            ])
            ->assertSessionHasErrors('start_date');

        $this->assertDatabaseCount('leave_requests', 0);
    }
}
