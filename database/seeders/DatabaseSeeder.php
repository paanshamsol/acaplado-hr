<?php

namespace Database\Seeders;

use App\Models\CompanyOperationalTime;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);
        $this->call(LeaveTypeSeeder::class);

        CompanySetting::updateOrCreate(['id' => 1], [
            'name' => 'Sambal Ijau Acap Lado',
            'address' => 'Kuala Lumpur, Malaysia',
            'phone' => '03-0000 0000',
            'email' => 'hr@acap-lado.test',
            'brand_color' => '#0b9322',
        ]);

        $company = CompanySetting::current();

        if (CompanyOperationalTime::query()->doesntExist()) {
            CompanyOperationalTime::create([
                'company_settings_id' => $company->id,
                'label' => 'Night Shift',
                'start_time' => '17:00',
                'end_time' => '03:30',
            ]);
        }

        $accounts = [
            ['System Admin', 'admin@acap-lado.test', 'admin01', 'admin'],
            ['Leesa Aziz', 'hr@acap-lado.test', 'hr001', 'hr'],
            ['Amsyar Hariz', 'lado01@acap-lado.test', 'lado01', 'staff'],
            ['Arif Najmi', 'lado02@acap-lado.test', 'lado02', 'staff'],
            ['AL Hassan', 'hassanshamsul425@gmail.com', 'lado03', 'staff'],
            ['Muhammad Hani Darwisy', 'lado04@acap-lado.test', 'lado04', 'staff'],
            ['Muhammad Alfi Aryan bin Mohd Azlan', 'lado05@acap-lado.test', 'lado05', 'staff'],
            ['Putri ariesya syuhada binti Abdul Rahman', 'lado06@acap-lado.test', 'lado06', 'staff'],
            ['Muhammad Al Husain Bin Shamsul', 'lado07@acap-lado.test', 'lado07', 'staff'],
            ['Mohamad Lutfi Muzaffar BIN BadrulHisham', 'lado08@acap-lado.test', 'lado08', 'staff'],
            ['Mahathir Hazizi Bin Mohd Suhaimi', 'lado09@acap-lado.test', 'lado09', 'staff'],
            ['faheem shamsol', 'lado10@acap-lado.test', 'lado10', 'staff'],
            ['Mirul Zahrin', 'staff00@acap-lado.test', 'staff00', 'staff'],
            ['Farhan Shamsol', 'test01@acap-lado.test', 'test01', 'staff'],
        ];

        foreach ($accounts as [$name, $email, $employeeId, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'employee_id' => $employeeId,
                    'password' => 'password',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ],
            );
            $user->syncRoles([$role]);
        }
    }
}
