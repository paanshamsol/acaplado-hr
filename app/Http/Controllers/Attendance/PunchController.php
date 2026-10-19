<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\PunchRequest;
use App\Models\CompanySetting;
use App\Models\User;
use App\Services\AttendancePuncher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PunchController extends Controller
{
    public function kiosk(): View
    {
        return view('attendance.kiosk', [
            'company' => CompanySetting::current(),
        ]);
    }

    public function punch(PunchRequest $request, AttendancePuncher $puncher): JsonResponse
    {
        /** @var User|null $staff */
        $staff = User::where('employee_id', $request->string('staff_id'))->first();

        if (! $staff || ! Hash::check($request->string('password'), $staff->password)) {
            return $this->fail('invalid_credentials', 'Invalid staff ID or password.');
        }

        if ($staff->status !== 'active') {
            return $this->fail('inactive', 'This staff account is inactive. Contact admin.');
        }

        if (! $staff->recordsAttendance()) {
            return $this->fail('not_applicable', 'Admin and HR accounts do not clock in or out.');
        }

        $result = $puncher->punch(
            $staff,
            $request->input('descriptor'),
            $request->input('image'),
            $request->ip(),
        );

        if (! $result['ok']) {
            return $this->fail($result['code'], $result['message']);
        }

        return response()->json([
            'ok' => true,
            'code' => 'ok',
            'name' => $staff->name,
            'photo' => $staff->profile_photo_path ? Storage::url($staff->profile_photo_path) : null,
            'type' => $result['type'],
            'time' => Carbon::now()->format('h:i A'),
            'message' => $result['message'],
        ]);
    }

    protected function fail(string $code, string $message): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ], 422);
    }
}
