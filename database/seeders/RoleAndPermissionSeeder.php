<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Create the application roles and permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'manage-company',
            'manage-staff',
            'view-attendance',
            'manage-attendance',
            'view-leaves',
            'approve-leaves',
            'manage-leaves',
            'clock-in',
            'apply-leaves',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::findOrCreate('admin', 'web');
        $hr = Role::findOrCreate('hr', 'web');
        $staff = Role::findOrCreate('staff', 'web');
        $contract = Role::findOrCreate('contract', 'web');
        $partTime = Role::findOrCreate('part-time', 'web');

        $admin->syncPermissions($permissions);

        $hr->syncPermissions([
            'manage-staff',
            'view-attendance',
            'manage-attendance',
            'view-leaves',
            'approve-leaves',
        ]);

        $staffPermissions = [
            'clock-in',
            'apply-leaves',
        ];

        $staff->syncPermissions($staffPermissions);
        $contract->syncPermissions($staffPermissions);
        $partTime->syncPermissions($staffPermissions);
    }
}
