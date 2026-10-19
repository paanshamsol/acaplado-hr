<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_can_create_a_staff_member(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.staff.store'), [
            'name' => 'Jane Doe',
            'employee_id' => 'EMP100',
            'email' => 'jane@acap-lado.test',
            'password' => 'Secret@123',
            'role' => 'staff',
            'pay_basis' => 'monthly',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.staff.index'));

        $staff = User::where('employee_id', 'EMP100')->firstOrFail();
        $this->assertSame('jane@acap-lado.test', $staff->email);
        $this->assertTrue($staff->hasRole('staff'));
    }

    public function test_deleted_staff_email_and_employee_id_can_be_reused(): void
    {
        $deleted = User::factory()->create([
            'employee_id' => 'EMP100',
            'email' => 'jane@acap-lado.test',
        ]);
        $deleted->delete();

        $this->actingAs($this->admin())->post(route('admin.staff.store'), [
            'name' => 'Jane Return',
            'employee_id' => 'EMP100',
            'email' => 'jane@acap-lado.test',
            'password' => 'Secret@123',
            'role' => 'staff',
            'pay_basis' => 'monthly',
            'status' => 'active',
        ])->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'employee_id' => 'EMP100',
            'email' => 'jane@acap-lado.test',
            'deleted_at' => null,
        ]);
    }

    public function test_employee_id_must_be_unique(): void
    {
        User::factory()->create(['employee_id' => 'EMP100']);

        $response = $this->actingAs($this->admin())->post(route('admin.staff.store'), [
            'name' => 'Jane Doe',
            'employee_id' => 'EMP100',
            'email' => 'jane@acap-lado.test',
            'password' => 'Secret@123',
            'role' => 'staff',
            'pay_basis' => 'monthly',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_admin_can_update_a_staff_member(): void
    {
        $staff = User::factory()->create(['employee_id' => 'EMP100']);

        $response = $this->actingAs($this->admin())->put(route('admin.staff.update', $staff), [
            'name' => 'Jane Updated',
            'employee_id' => 'EMP100',
            'email' => $staff->email,
            'role' => 'hr',
            'pay_basis' => 'hourly',
            'hourly_rate' => 25.5,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.staff.index'));

        $staff->refresh();
        $this->assertSame('Jane Updated', $staff->name);
        $this->assertTrue($staff->hasRole('hr'));
    }

    public function test_admin_can_assign_contract_and_part_time_roles(): void
    {
        foreach (['contract', 'part-time'] as $role) {
            $employeeId = strtoupper($role).'100';

            $this->actingAs($this->admin())->post(route('admin.staff.store'), [
                'name' => 'Jane '.ucfirst($role),
                'employee_id' => $employeeId,
                'email' => str_replace('-', '', $role).'@acap-lado.test',
                'password' => 'Secret@123',
                'role' => $role,
                'pay_basis' => 'monthly',
                'status' => 'active',
            ])->assertRedirect(route('admin.staff.index'));

            $staff = User::where('employee_id', $employeeId)->firstOrFail();
            $this->assertTrue($staff->hasRole($role));
            $this->assertTrue($staff->can('clock-in'));
        }
    }

    public function test_admin_can_change_staff_password_without_current_password(): void
    {
        $staff = User::factory()->create(['employee_id' => 'EMP100', 'password' => Hash::make('oldpass123')]);

        $this->actingAs($this->admin())->put(route('admin.staff.update', $staff), [
            'name' => $staff->name,
            'employee_id' => 'EMP100',
            'email' => $staff->email,
            'role' => 'staff',
            'pay_basis' => 'monthly',
            'status' => 'active',
            'password' => 'Newpass@123',
        ])->assertRedirect(route('admin.staff.index'));

        $this->assertTrue(Hash::check('Newpass@123', $staff->refresh()->password));
        $this->assertFalse(Hash::check('oldpass123', $staff->password));
    }

    public function test_admin_can_change_staff_password_without_losing_the_old_password_when_new_password_omitted(): void
    {
        $staff = User::factory()->create(['employee_id' => 'EMP100', 'password' => Hash::make('oldpass123')]);

        $this->actingAs($this->admin())->put(route('admin.staff.update', $staff), [
            'name' => 'Renamed Staff',
            'employee_id' => 'EMP100',
            'email' => $staff->email,
            'role' => 'staff',
            'pay_basis' => 'monthly',
            'status' => 'active',
        ])->assertRedirect(route('admin.staff.index'));

        $this->assertSame('Renamed Staff', $staff->refresh()->name);
        $this->assertTrue(Hash::check('oldpass123', $staff->password));
    }

    public function test_current_password_field_is_ignored_when_changing_a_staff_password(): void
    {
        $staff = User::factory()->create(['employee_id' => 'EMP100', 'password' => Hash::make('oldpass123')]);

        $this->actingAs($this->admin())->put(route('admin.staff.update', $staff), [
            'name' => $staff->name,
            'employee_id' => 'EMP100',
            'email' => $staff->email,
            'role' => 'staff',
            'pay_basis' => 'monthly',
            'status' => 'active',
            'current_password' => 'totally-wrong-guess',
            'password' => 'Newpass@123',
        ])->assertRedirect(route('admin.staff.index'));

        $this->assertTrue(Hash::check('Newpass@123', $staff->refresh()->password));
    }

    public function test_edit_staff_page_shows_the_current_password(): void
    {
        $staff = User::factory()->create([
            'employee_id' => 'EMP100',
            'password' => 'Viewable@123',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.staff.edit', $staff))
            ->assertOk()
            ->assertSee('Current Password')
            ->assertSee('Viewable@123');
    }

    public function test_edit_staff_page_shows_not_available_when_no_stored_password_copy(): void
    {
        $staff = User::factory()->create([
            'employee_id' => 'EMP100',
            'password' => Hash::make('unknown-pass'),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.staff.edit', $staff))
            ->assertOk()
            ->assertSee('Not available');
    }

    public function test_stored_password_copy_is_updated_when_admin_changes_password(): void
    {
        $staff = User::factory()->create(['employee_id' => 'EMP100']);

        $this->actingAs($this->admin())->put(route('admin.staff.update', $staff), [
            'name' => $staff->name,
            'employee_id' => 'EMP100',
            'email' => $staff->email,
            'role' => 'staff',
            'pay_basis' => 'monthly',
            'status' => 'active',
            'password' => 'Newpass@123',
        ])->assertRedirect(route('admin.staff.index'));

        $staff->refresh();

        $this->assertSame('Newpass@123', $staff->visiblePassword());
        $this->assertTrue(Hash::check('Newpass@123', $staff->password));
    }

    public function test_weak_password_is_rejected_for_new_and_updated_staff(): void
    {
        $staff = User::factory()->create(['employee_id' => 'EMP100']);

        $this->actingAs($this->admin())->post(route('admin.staff.store'), [
            'name' => 'Weak Password',
            'employee_id' => 'EMP200',
            'email' => 'weak@acap-lado.test',
            'password' => 'alllower123',
            'role' => 'staff',
            'pay_basis' => 'monthly',
            'status' => 'active',
        ])->assertSessionHasErrors('password');

        $this->actingAs($this->admin())->put(route('admin.staff.update', $staff), [
            'name' => $staff->name,
            'employee_id' => 'EMP100',
            'email' => $staff->email,
            'role' => 'staff',
            'pay_basis' => 'monthly',
            'status' => 'active',
            'password' => 'Pass@1',
        ])->assertSessionHasErrors('password');
    }

    public function test_department_and_position_are_discarded_on_update(): void
    {
        $staff = User::factory()->create([
            'employee_id' => 'EMP100',
            'department' => 'Original Department',
            'position' => 'Original Position',
        ]);

        $this->actingAs($this->admin())->put(route('admin.staff.update', $staff), [
            'name' => 'Jane',
            'employee_id' => 'EMP100',
            'email' => $staff->email,
            'role' => 'staff',
            'pay_basis' => 'monthly',
            'status' => 'active',
            'department' => 'Should Be Ignored',
            'position' => 'Should Be Ignored',
        ]);

        $staff->refresh();
        $this->assertSame('Original Department', $staff->department);
        $this->assertSame('Original Position', $staff->position);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->delete(route('admin.staff.destroy', $admin));

        $response->assertSessionHas('status', 'cannot-delete-self');
        $this->assertNotSoftDeleted($admin);
    }

    public function test_staff_cannot_manage_staff(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('admin.staff.index'))
            ->assertForbidden();
    }

    public function test_admin_can_upload_staff_profile_photo(): void
    {
        Storage::fake('public');

        $staff = User::factory()->create(['employee_id' => 'EMP100']);

        $this->actingAs($this->admin())->put(route('admin.staff.update', $staff), [
            'name' => 'Jane',
            'employee_id' => 'EMP100',
            'email' => $staff->email,
            'role' => 'staff',
            'pay_basis' => 'monthly',
            'status' => 'active',
            'profile_photo' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertRedirect(route('admin.staff.index'));

        $staff->refresh();

        $this->assertNotNull($staff->profile_photo_path);
        Storage::disk('public')->assertExists($staff->profile_photo_path);
    }

    public function test_admin_can_upload_profile_photo_from_the_staff_list(): void
    {
        Storage::fake('public');

        $staff = User::factory()->create(['employee_id' => 'EMP100']);

        $this->actingAs($this->admin())
            ->post(route('admin.staff.photo', $staff), [
                'profile_photo' => UploadedFile::fake()->image('list-photo.jpg'),
            ])
            ->assertRedirect(route('admin.staff.index'));

        $staff->refresh();

        $this->assertNotNull($staff->profile_photo_path);
        Storage::disk('public')->assertExists($staff->profile_photo_path);
    }

    public function test_uploading_a_new_photo_removes_the_previous_one(): void
    {
        Storage::fake('public');

        $oldPath = UploadedFile::fake()->image('old.jpg')->store('profiles', 'public');
        $staff = User::factory()->create(['employee_id' => 'EMP100', 'profile_photo_path' => $oldPath]);

        $this->actingAs($this->admin())
            ->post(route('admin.staff.photo', $staff), [
                'profile_photo' => UploadedFile::fake()->image('new.jpg'),
            ])
            ->assertRedirect(route('admin.staff.index'));

        $staff->refresh();

        $this->assertNotSame($oldPath, $staff->profile_photo_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($staff->profile_photo_path);
    }

    public function test_staff_cannot_upload_another_staff_photo(): void
    {
        Storage::fake('public');

        $staff = User::factory()->create(['employee_id' => 'EMP100']);
        $other = User::factory()->create(['employee_id' => 'EMP200']);
        $other->assignRole('staff');

        $this->actingAs($other)
            ->post(route('admin.staff.photo', $staff), [
                'profile_photo' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertForbidden();

        $this->assertNull($staff->fresh()->profile_photo_path);
    }
}
