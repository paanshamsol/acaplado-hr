<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;
use Throwable;

#[Fillable([
    'employee_id',
    'name',
    'email',
    'phone',
    'password',
    'profile_photo_path',
    'face_descriptor',
    'department',
    'position',
    'join_date',
    'hourly_rate',
    'hours_of_work',
    'days_of_work',
    'status',
    'address',
    'ic_number',
])]
#[Hidden(['password', 'password_plain', 'remember_token', 'face_descriptor'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'face_descriptor' => 'array',
            'join_date' => 'date',
            'hourly_rate' => 'decimal:2',
            'hours_of_work' => 'decimal:2',
            'days_of_work' => 'integer',
        ];
    }

    /**
     * Hash the password on write and keep an encrypted, viewable copy.
     *
     * Pre-hashed values (already bcrypt/argon hashes) are stored as-is.
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            set: function (mixed $value): mixed {
                if (! is_string($value) || $value === '' || Hash::isHashed($value)) {
                    return $value;
                }

                return [
                    'password_plain' => Crypt::encryptString($value),
                    'password' => Hash::make($value),
                ];
            },
        );
    }

    /**
     * The readable copy of the user's current password (null when unknown).
     */
    public function visiblePassword(): ?string
    {
        $stored = $this->attributes['password_plain'] ?? null;

        if (blank($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }

    public function hasFaceEnrolled(): bool
    {
        return ! empty($this->face_descriptor);
    }

    /**
     * Users who clock in/out: everyone except system admins and HR.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeAttendanceStaff(Builder $query): Builder
    {
        return $query->whereDoesntHave('roles', function (Builder $query) {
            $query->whereIn('name', ['admin', 'hr']);
        });
    }

    /**
     * Users who hold leave records and entitlements: everyone except system
     * admins and HR.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeLeaveStaff(Builder $query): Builder
    {
        return $query->whereDoesntHave('roles', function (Builder $query) {
            $query->whereIn('name', ['admin', 'hr']);
        });
    }

    /**
     * Whether the user records attendance (false for admins and HR).
     */
    public function recordsAttendance(): bool
    {
        return ! $this->hasAnyRole(['admin', 'hr']);
    }

    /**
     * Whether the user holds leave records and entitlements (false for admins
     * and HR).
     */
    public function recordsLeave(): bool
    {
        return ! $this->hasAnyRole(['admin', 'hr']);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @return HasMany<LeaveBalance, $this>
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<SalaryAdjustment, $this>
     */
    public function salaryAdjustments(): HasMany
    {
        return $this->hasMany(SalaryAdjustment::class);
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
