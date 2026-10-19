<?php

namespace Tests\Feature;

use App\Models\CompanyOperationalTime;
use App\Models\CompanySetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    /**
     * A complete, valid company-settings payload (all fields are required).
     */
    private function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'AcapLado Sdn Bhd',
            'address' => '1 Jalan Test, 50000 Kuala Lumpur',
            'phone' => '0312345678',
            'email' => 'hr@acap-lado.test',
            'brand_color' => '#4f46e5',
            'latitude' => 3.139003,
            'longitude' => 101.686855,
            'operational_times' => [
                ['label' => 'Day Shift', 'start_time' => '09:00', 'end_time' => '18:00'],
            ],
        ], $overrides);
    }

    public function test_admin_is_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_staff_is_redirected_to_staff_dashboard(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_admin_can_view_company_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.company.edit'))
            ->assertOk();
    }

    public function test_company_edit_form_binds_operational_time_field_names(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.company.edit'))
            ->assertOk()
            ->assertSee("operational_times[' + index + '][label]", false)
            ->assertSee("operational_times[' + index + '][start_time]", false)
            ->assertSee("operational_times[' + index + '][end_time]", false);
    }

    public function test_hr_cannot_view_company_settings(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        $this->actingAs($hr)
            ->get(route('admin.company.edit'))
            ->assertForbidden();
    }

    public function test_staff_cannot_access_admin_dashboard(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_update_company_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.company.update'), $this->companyPayload([
                'name' => 'AcapLado Sdn Bhd',
            ]))
            ->assertRedirect(route('admin.company.edit'));

        $this->assertDatabaseHas('company_settings', [
            'name' => 'AcapLado Sdn Bhd',
            'address' => '1 Jalan Test, 50000 Kuala Lumpur',
            'phone' => '0312345678',
            'email' => 'hr@acap-lado.test',
        ]);
    }

    public function test_admin_cannot_update_company_settings_with_missing_required_fields(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->from(route('admin.company.edit'))
            ->put(route('admin.company.update'), ['name' => 'AcapLado Sdn Bhd'])
            ->assertSessionHasErrors([
                'address',
                'phone',
                'email',
                'latitude',
                'longitude',
                'operational_times',
            ]);
    }

    public function test_admin_can_save_brand_color(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.company.update'), $this->companyPayload([
                'brand_color' => '#ff8800',
            ]))
            ->assertRedirect(route('admin.company.edit'));

        $this->assertDatabaseHas('company_settings', ['brand_color' => '#ff8800']);
        $this->assertSame('#ff8800', CompanySetting::current()->brandColor());
    }

    public function test_company_brand_color_defaults_to_indigo(): void
    {
        $this->assertSame(CompanySetting::DEFAULT_BRAND_COLOR, CompanySetting::current()->brandColor());
    }

    public function test_brand_color_is_applied_on_company_settings_page(): void
    {
        $company = CompanySetting::current();
        $company->update(['brand_color' => '#ff8800']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.company.edit'))
            ->assertOk()
            ->assertSee('--brand: #ff8800', false);
    }

    public function test_admin_can_add_operational_time_through_company_update(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.company.update'), $this->companyPayload([
                'operational_times' => [
                    ['label' => 'Night Shift', 'start_time' => '17:00', 'end_time' => '03:00'],
                ],
            ]))
            ->assertRedirect(route('admin.company.edit'));

        $this->assertDatabaseHas('company_operational_times', [
            'label' => 'Night Shift',
            'start_time' => '17:00',
            'end_time' => '03:00',
        ]);
    }

    public function test_admin_cannot_save_identical_start_and_end_times(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->from(route('admin.company.edit'))
            ->put(route('admin.company.update'), $this->companyPayload([
                'operational_times' => [
                    ['label' => 'Invalid', 'start_time' => '17:00', 'end_time' => '17:00'],
                ],
            ]))
            ->assertSessionHasErrors('operational_times.0.end_time');

        $this->assertDatabaseMissing('company_operational_times', [
            'label' => 'Invalid',
        ]);
    }

    public function test_admin_can_update_operational_time_through_company_update(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $time = CompanyOperationalTime::create([
            'company_settings_id' => CompanySetting::current()->id,
            'label' => 'Day Shift',
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.company.update'), $this->companyPayload([
                'operational_times' => [
                    ['id' => $time->id, 'label' => 'Night Shift', 'start_time' => '17:00', 'end_time' => '03:00'],
                ],
            ]))
            ->assertRedirect(route('admin.company.edit'));

        $this->assertDatabaseHas('company_operational_times', [
            'id' => $time->id,
            'label' => 'Night Shift',
            'start_time' => '17:00',
            'end_time' => '03:00',
        ]);
    }

    public function test_admin_can_remove_operational_time_through_company_update(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $companyId = CompanySetting::current()->id;

        $keep = CompanyOperationalTime::create([
            'company_settings_id' => $companyId,
            'label' => 'Keep Shift',
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);

        $removed = CompanyOperationalTime::create([
            'company_settings_id' => $companyId,
            'label' => 'Remove Shift',
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.company.update'), $this->companyPayload([
                'operational_times' => [
                    ['id' => $keep->id, 'label' => 'Keep Shift', 'start_time' => '10:00', 'end_time' => '14:00'],
                ],
            ]))
            ->assertRedirect(route('admin.company.edit'));

        $this->assertDatabaseHas('company_operational_times', [
            'id' => $keep->id,
        ]);
        $this->assertDatabaseMissing('company_operational_times', [
            'id' => $removed->id,
        ]);
    }

    public function test_admin_can_save_company_location(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.company.update'), $this->companyPayload([
                'latitude' => 3.139003,
                'longitude' => 101.686855,
            ]))
            ->assertRedirect(route('admin.company.edit'));

        $this->assertDatabaseHas('company_settings', [
            'name' => 'AcapLado Sdn Bhd',
            'latitude' => 3.139003,
            'longitude' => 101.686855,
        ]);
    }

    public function test_admin_cannot_save_out_of_range_coordinates(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->from(route('admin.company.edit'))
            ->put(route('admin.company.update'), $this->companyPayload([
                'latitude' => 95,
                'longitude' => 200,
            ]))
            ->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_hr_cannot_manage_operational_times(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        $this->actingAs($hr)
            ->put(route('admin.company.update'), $this->companyPayload([
                'operational_times' => [
                    ['label' => 'Blocked', 'start_time' => '17:00', 'end_time' => '03:00'],
                ],
            ]))
            ->assertForbidden();
    }

    public function test_admin_can_upload_company_logo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.company.update'), $this->companyPayload([
                'logo' => UploadedFile::fake()->image('logo.png'),
            ]))
            ->assertRedirect(route('admin.company.edit'));

        $company = CompanySetting::current();

        $this->assertNotNull($company->logo_path);
        Storage::disk('public')->assertExists($company->logo_path);
    }

    public function test_admin_can_remove_company_logo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $path = UploadedFile::fake()->image('logo.png')->store('logos', 'public');

        CompanySetting::current()->update(['logo_path' => $path]);

        $this->actingAs($admin)
            ->put(route('admin.company.update'), $this->companyPayload([
                'remove_logo' => 1,
            ]))
            ->assertRedirect(route('admin.company.edit'));

        $this->assertNull(CompanySetting::current()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_company_logo_appears_on_staff_login_page(): void
    {
        Storage::fake('public');

        $path = UploadedFile::fake()->image('logo.png')->store('logos', 'public');

        CompanySetting::current()->update(['logo_path' => $path]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($path), false);
    }

    public function test_company_logo_data_uri_embeds_the_uploaded_logo_in_pdfs(): void
    {
        Storage::fake('public');

        $path = UploadedFile::fake()->image('logo.png')->store('logos', 'public');

        CompanySetting::current()->update(['logo_path' => $path]);

        $this->assertStringStartsWith(
            'data:image/png;base64,',
            (string) CompanySetting::current()->logoDataUri(),
        );
    }

    public function test_audit_logs_route_requires_only_manage_company(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.audit-logs.index');
        $middleware = implode(' ', $route->gatherMiddleware());

        $this->assertStringContainsString('permission:manage-company', $middleware);
    }

    public function test_admin_can_view_audit_logs_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk();
    }

    public function test_admin_pages_render_without_server_errors(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $pages = [
            'admin.dashboard',
            'admin.attendance.index',
            'admin.attendance.data',
            'admin.attendance.export',
            'admin.audit-logs.index',
            'admin.company.edit',
            'admin.leave-requests.index',
            'admin.leave-requests.export',
            'admin.leaves.index',
            'admin.staff.index',
            'admin.staff.create',
            'notifications.index',
            'profile.edit',
        ];

        foreach ($pages as $name) {
            $this->actingAs($admin)
                ->get(route($name))
                ->assertOk("Page [$name] did not render.");
        }
    }
}
