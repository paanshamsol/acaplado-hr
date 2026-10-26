<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\CompanySetting;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Support\PayslipSync;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value();

        $staff = User::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.staff.index', compact('staff', 'search'));
    }

    public function create(): View
    {
        return view('admin.staff.create');
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['profile_photo'], $data['face_descriptor']);

        $role = $data['role'];
        unset($data['role']);

        $data['face_descriptor'] = $this->extractDescriptor($request->input('face_descriptor'));

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo_path'] = $request->file('profile_photo')->store('profiles', 'public');
        }

        $staff = User::create($data);
        $staff->syncRoles([$role]);

        return redirect()
            ->route('admin.staff.index')
            ->with('status', 'staff-created');
    }

    public function edit(Request $request, User $staff): View
    {
        $adjustments = $staff->salaryAdjustments()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        $latest = $adjustments->first();

        $year = (int) ($request->integer('year') ?: ($latest->year ?? now()->year));
        $month = (int) ($request->integer('month') ?: ($latest->month ?? now()->month));
        $month = ($month >= 1 && $month <= 12) ? $month : (int) now()->month;

        $adjustment = $adjustments->first(
            fn ($adjustment) => $adjustment->year === $year && $adjustment->month === $month,
        );

        $salary = [
            'year' => $year,
            'month' => $month,
            'rate' => (float) ($adjustment->hourly_rate ?? $staff->hourly_rate ?? 0),
            'hours' => (float) ($adjustment->hours_of_work ?? $staff->hours_of_work ?? 0),
            'days' => (int) ($adjustment->days_of_work ?? $staff->days_of_work ?? 0),
        ];

        return view('admin.staff.edit', compact('staff', 'adjustments', 'salary'));
    }

    public function update(UpdateStaffRequest $request, User $staff): RedirectResponse
    {
        $data = $request->validated();
        unset($data['profile_photo'], $data['face_descriptor']);

        $role = $data['role'];
        unset($data['role']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $descriptor = $this->extractDescriptor($request->input('face_descriptor'));
        if ($descriptor !== null) {
            $data['face_descriptor'] = $descriptor;
        }

        if ($request->hasFile('profile_photo')) {
            if (filled($staff->profile_photo_path)) {
                Storage::disk('public')->delete($staff->profile_photo_path);
            }

            $data['profile_photo_path'] = $request->file('profile_photo')->store('profiles', 'public');
        }

        $staff->update($data);
        $staff->syncRoles([$role]);

        return redirect()
            ->route('admin.staff.index')
            ->with('status', 'staff-updated');
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        if ($request->user()->is($staff)) {
            return back()->with('status', 'cannot-delete-self');
        }

        $staff->delete();

        return redirect()
            ->route('admin.staff.index')
            ->with('status', 'staff-deleted');
    }

    /**
     * Quickly set or replace a staff member's profile photo from the list.
     */
    public function updatePhoto(Request $request, User $staff): RedirectResponse
    {
        $request->validate([
            'profile_photo' => ['required', 'image', 'max:4096'],
        ]);

        $this->replaceProfilePhoto($staff, $request->file('profile_photo'));

        return redirect()
            ->route('admin.staff.edit', ['staff' => $staff, 'sync' => 1])
            ->with('status', 'staff-photo-updated');
    }

    /**
     * Save the salary adjustment (rate / hours / days) for a staff member and
     * period. The adjustment recalculates that period's payroll record.
     */
    public function saveSalaryAdjustment(Request $request, User $staff): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'hours_of_work' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'days_of_work' => ['required', 'integer', 'min:0', 'max:366'],
        ]);

        $year = (int) $data['year'];
        $month = (int) $data['month'];

        $adjustment = SalaryAdjustment::updateOrCreate(
            [
                'user_id' => $staff->id,
                'year' => $year,
                'month' => $month,
            ],
            [
                'hourly_rate' => $data['hourly_rate'],
                'hours_of_work' => $data['hours_of_work'],
                'days_of_work' => $data['days_of_work'],
            ],
        );

        $staff->update([
            'hourly_rate' => $data['hourly_rate'],
            'hours_of_work' => $data['hours_of_work'],
            'days_of_work' => $data['days_of_work'],
        ]);

        PayslipSync::fromAdjustment($adjustment, auth()->id());

        if ($request->input('context') === 'dashboard') {
            $params = ['salary_year' => $year, 'salary_month' => $month];

            if ($request->filled('return_year')) {
                $params['year'] = (int) $request->integer('return_year');
            }

            return redirect()
                ->route('admin.dashboard', $params)
                ->with('status', 'salary-updated')
                ->with('saved_period', Carbon::create($year, $month, 1)->format('F Y'))
                ->with('saved_staff', $staff->name);
        }

        return redirect()
            ->route('admin.staff.edit', ['staff' => $staff, 'year' => $year, 'month' => $month])
            ->with('status', 'salary-updated')
            ->with('saved_period', Carbon::create($year, $month, 1)->format('F Y'));
    }

    /**
     * Download the payslip PDF for a staff member and period, generating or
     * refreshing the stored payslip from the saved salary adjustment first.
     */
    public function payslipPdf(Request $request, User $staff): mixed
    {
        $year = (int) ($request->integer('year') ?: now()->year);
        $month = (int) ($request->integer('month') ?: now()->month);
        $month = ($month >= 1 && $month <= 12) ? $month : (int) now()->month;

        $adjustment = $staff->salaryAdjustments()
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        abort_if($adjustment === null, 404);

        $payslip = PayslipSync::fromAdjustment($adjustment, auth()->id());

        $payslip->load('user', 'generator');

        $pdf = Pdf::loadView('pdf.payslip', [
            'payslip' => $payslip,
            'company' => CompanySetting::current(),
        ]);

        $identifier = $staff->employee_id ?: 'staff';

        return $pdf->download(sprintf('payslip-%s-%d-%02d.pdf', $identifier, $year, $month));
    }

    /**
     * Store a new profile photo and remove the previous one.
     */
    protected function replaceProfilePhoto(User $staff, UploadedFile $photo): void
    {
        if (filled($staff->profile_photo_path)) {
            Storage::disk('public')->delete($staff->profile_photo_path);
        }

        $staff->update([
            'profile_photo_path' => $photo->store('profiles', 'public'),
        ]);
    }

    /**
     * Decode a JSON face descriptor captured in the browser.
     *
     * @return array<int, float>|null
     */
    protected function extractDescriptor(?string $json): ?array
    {
        if (blank($json)) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) || count($decoded) < 64) {
            return null;
        }

        return array_map('floatval', array_values($decoded));
    }
}
