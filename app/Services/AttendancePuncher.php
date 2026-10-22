<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class AttendancePuncher
{
    /**
     * Verify a captured face against the staff profile and record a punch.
     *
     * @param  array<int, float|int>  $descriptor
     * @return array{ok: bool, code: string, message: string, type?: string, distance?: float, attendance?: Attendance}
     */
    public function punch(User $user, array $descriptor, ?string $imageDataUrl, ?string $ip): array
    {
        if (! $user->hasFaceEnrolled()) {
            return [
                'ok' => false,
                'code' => 'no_face',
                'message' => 'No face profile has been enrolled for this staff member.',
            ];
        }

        $threshold = (float) config('hr.face_match_threshold', 0.6);
        $distance = FaceMatcher::distance($user->face_descriptor, $descriptor);

        if ($distance > $threshold) {
            return [
                'ok' => false,
                'code' => 'mismatch',
                'distance' => $distance,
                'message' => 'Face does not match this staff profile.',
            ];
        }

        $now = Carbon::now();
        $settings = CompanySetting::current();
        $openPredicate = fn ($query) => $query->whereNull('clock_out_at')->whereNotNull('clock_in_at');

        // A punch made after midnight still belongs to the overnight shift that
        // started the previous day: it closes an open record when there is one,
        // otherwise it opens the previous day's record (a late clock-in) rather
        // than starting a brand-new attendance day.
        $workDate = $settings->attendanceWorkDateFor($now);
        $isOvernightTail = ! $workDate->isSameDay($now);

        $reusePrevious = false;

        if ($isOvernightTail) {
            $previousOpen = Attendance::query()
                ->where('user_id', $user->id)
                ->whereDate('work_date', $workDate->toDateString())
                ->tap($openPredicate)
                ->exists();

            if ($previousOpen) {
                $reusePrevious = true;
            }
        }

        // An open record from the previous day always wins: the next punch
        // closes it rather than leaving a stray day open, regardless of the
        // hour (e.g. an overnight shift forgotten until the morning).
        if (! $reusePrevious) {
            $previousOpen = Attendance::query()
                ->where('user_id', $user->id)
                ->whereDate('work_date', $now->copy()->subDay()->toDateString())
                ->tap($openPredicate)
                ->exists();

            if ($previousOpen) {
                $reusePrevious = true;
                $workDate = $now->copy()->subDay();
            }
        }

        // A punch within the cooldown of the last clock-out is too soon to be a
        // new shift, so it is rejected rather than opening a second record.
        if (! $reusePrevious) {
            $nextShiftAt = $settings->nextShiftStartAt($now->copy()->startOfDay());

            if ($nextShiftAt !== null && $now->lessThan($nextShiftAt)) {
                $remaining = (int) ceil($now->diffInMinutes($nextShiftAt, false));

                return [
                    'ok' => false,
                    'code' => 'cooldown',
                    'distance' => $distance,
                    'message' => "You clocked out too recently. You can clock in again in {$remaining} minute(s).",
                ];
            }
        }

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate->toDateString())
            ->first();

        if ($reusePrevious) {
            $previous = Attendance::query()
                ->where('user_id', $user->id)
                ->whereDate('work_date', $workDate->toDateString())
                ->tap($openPredicate)
                ->latest('clock_in_at')
                ->first();

            if ($previous !== null) {
                $attendance = $previous;
            }
        }

        if ($attendance === null) {
            $attendance = new Attendance([
                'user_id' => $user->id,
                'work_date' => $workDate->copy()->startOfDay(),
            ]);
        }

        if ($attendance->exists && $attendance->clock_out_at !== null) {
            return [
                'ok' => false,
                'code' => 'already_out',
                'distance' => $distance,
                'message' => 'You have already clocked out today.',
            ];
        }

        $photoPath = $this->storePhoto($user, $imageDataUrl, $now);

        if ($attendance->exists && $attendance->clock_in_at !== null) {
            $attendance->fill([
                'clock_out_at' => $now,
                'clock_out_photo_path' => $photoPath,
                'clock_out_face_score' => $distance,
                'clock_out_ip' => $ip,
                'worked_minutes' => (int) $attendance->clock_in_at->diffInMinutes($now, false),
                'status' => 'closed',
            ])->save();

            $type = 'out';
        } else {
            $attendance->fill([
                'clock_in_at' => $now,
                'clock_in_photo_path' => $photoPath,
                'clock_in_face_score' => $distance,
                'clock_in_ip' => $ip,
                'status' => 'open',
            ])->save();

            $type = 'in';
        }

        return [
            'ok' => true,
            'code' => 'ok',
            'type' => $type,
            'distance' => $distance,
            'attendance' => $attendance,
            'message' => $type === 'in' ? 'Clock-in recorded.' : 'Clock-out recorded.',
        ];
    }

    /**
     * Persist a base64 data URL snapshot to the public disk.
     */
    protected function storePhoto(User $user, ?string $dataUrl, Carbon $now): ?string
    {
        if (! $dataUrl || ! preg_match('/^data:image\/(\w+);base64,/', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strlen($matches[0])), true);

        if ($binary === false) {
            return null;
        }

        $extension = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
        $path = "attendance/{$user->id}/{$now->format('Ymd_His')}.{$extension}";

        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
