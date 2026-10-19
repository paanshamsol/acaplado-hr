# AcapLado HR — Staff Attendance & Leave System

Plan for a single-company staff clock-in/clock-out, leave, and payroll system
for **AcapLado Company**. Timezone: **Asia/Kuala_Lumpur (MYT)**.

## Decisions (confirmed)

| Topic | Decision |
|---|---|
| Stack | Laravel 13 + Blade + Livewire (monolith) |
| Auth | Breeze (Blade) + spatie/laravel-permission |
| Tenancy | Single company only |
| Database | SQLite (dev) |
| Face verification | Automated facial recognition |
| Camera source | Browser webcam (`getUserMedia`) |
| Payroll | Auto-calc from hours, admin-editable lines |
| Leave approval | HR and Admin can approve/reject |
| Attendance status | On-time if clock-in ≤ operational start + 5 min grace; else late; no clock-in = absent. A day with no clock-in/out at all (e.g. a future day) shows "No record". (grace via `hr.late_grace_minutes`; falls back to `hr.late_clock_in`, default 09:00, when no shift is configured) |
| Charts | Chart.js via npm |
| Deployment | TBD — keep deployment-agnostic |
| Sequencing | MVP first, then payroll |

## Progress

- [x] **Phase 0 — Scaffold** (Laravel 13, Breeze auth, roles/permissions, MYT
  timezone, company settings, base admin/staff layouts)
- [x] **Phase 1 — Core attendance**
  - [x] Login by staff id or email
  - [x] Admin staff CRUD + face enrollment (webcam)
  - [x] Public clock-in/out kiosk (`/attendance`) with staff id + password +
        webcam snapshot + automated face verification
  - [x] Realtime daily/monthly attendance dashboard (10s polling)
- [x] **Phase 1.5 — Company operational setup**
  - [x] Operational times (multi-window, overnight-aware) managed inside one
        single-Save Company Information form
  - [x] Exact company location via Google Maps embed (lat/lng, no API key) +
        "use my location"
  - [x] Camera error handling / secure-context guidance on the kiosk
  - [x] Dashboard MYT date fix, session/CSRF (419) fix, merged Profile page,
        Company nav moved to the top-right user dropdown
- [x] **Phase 1.6 — Admin UX, attendance status & company branding**
  - [x] Company **logo upload** in the Company section (PNG/JPG/SVG/WebP, 2 MB,
        removable) — shown on the staff login page, admin dashboard
        (top nav + sidebar) and the clock-in kiosk; falls back to
        `public/images/company-logo.*` then the app logo
  - [x] Larger logos on the kiosk (`h-28`) and staff login page (`h-24`)
  - [x] Configurable **brand colour** (Company section colour picker,
        `company_settings.brand_color`) applied as the `--brand` CSS variable to
        the top nav / sidebar active states, primary buttons and dashboard
        accents; defaults to indigo (`#4f46e5`)
  - [x] Attendance **status** on the admin dashboard = On time / Late / Absent,
        derived from the operational shift start + 5-minute grace
        (`config('hr.late_grace_minutes')`); no clock-in = absent
  - [x] **Admins and HR are excluded from attendance** — they do not clock in/out
        (rejected at the kiosk) and are omitted from the attendance record,
        roster, dashboard breakdown/chart and the "present today" stat
        (`User::scopeAttendanceStaff` / `recordsAttendance`)
  - [x] Admin dashboard **attendance doughnut chart** (Chart.js) — today's
        On time / Late / Absent breakdown + numeric totals
  - [x] **Staff profile photo upload from the staff list** (clickable avatar /
        "Photo" action), replacing and deleting the previous file
  - [x] Removed "Main Dashboard" and "Clock-in Kiosk" links from the admin
        sidebar
- [x] **Phase 2 — Leave**
  - [x] Leave types + per-staff balances (`leave_types`, `leave_balances`)
  - [x] Admin dashboard leave matrix (per staff, per leave type) + edit/delete
        entitlements (`/admin/leaves`); shows **Used**, **Remaining**,
        **Carried over** and totals per person (admin, HR and staff)
  - [x] **Apply flow for staff** — `/staff/leaves` (entitlements summary +
        apply form + request history); validation covers date order,
        attachment-required types and available balance (`leave_requests`)
  - [x] **HR/Admin approve/reject** — `/admin/leave-requests` (pending /
        approved / rejected tabs); approval records the approver and adds the
        days to the staff balance, rejection stores an optional reason
  - [x] **Staff portal view of entitlements** — staff dashboard + My Leave page
  - [x] **Year selection** — the admin dashboard and Leave Entitlements page can
        view any year (previous/upcoming) and show that year's company-given
        entitlements per staff (`?year=YYYY`)
  - [x] **Year roll-over** (`LeaveRollover::generate`, "Generate <year>" button on
        `/admin/leaves`) — annual leave renews to the company default and carries
        up to **3** unused days into the next year; medical (14), emergency (3)
        and bereavement (3) renew each year; existing used days in the target
        year are preserved
  - [x] **Annual covers the shortfall** — when medical/emergency/bereavement leave
        is exhausted, the surplus is deducted from the staff member's annual
        leave (applied on approval and allowed when applying)
- [x] Phase 3 — Payroll
  - [x] Hours → gross aggregation
  - [x] Salary adjustment stored per staff per period (`SalaryAdjustment`: rate/hours/days)
  - [x] Salary adjustment edited **on the staff edit page** (period picker loads any saved period,
        live basic-pay preview, per-row period history) — the separate admin Salary page was removed
  - [x] Salary adjustment also editable **on the admin dashboard** in a
        "Salary & Payslips" section: one row per employee with rate (RM/hour) ×
        total hours × total days inputs, a live basic-pay total per row, a
        manual month/year period picker (period badge + total payroll footer)
        and a per-row **Save**
  - [x] Saving recalculates that period's `payslips` record and keeps it in sync
        (dashboard or staff edit page); refreshes never wipe existing allowances /
        other deductions — only rate/hours/days, basic and net are recalculated
  - [x] Downloadable company-branded **payslip PDF** per employee/period
        (`admin.staff.payslip.pdf`) containing the company info (name, address,
        phone, email, logo) plus the saved salary adjustment; opening it
        auto-generates/refreshes the stored payslip from the adjustment
  - [x] No bulk payslip screens (generate/list/bulk publish/edit/CSV) — the
        `manage-payslips` permission is gone and payroll actions are gated by
        `manage-staff`; the staff portal keeps a **read-only "My Payslips"** page
        (own periods only) with a per-period PDF download
  - [x] EPF/SOCSO/EIS/PCB contributions removed — net pay = basic + allowances − other deductions
  - [x] Staff portal: today's attendance status (on-time/late/absent/no record) from attendance record
- [ ] Phase 4 — Polish
  - [x] System-wide error/bug audit: every route + permission, Blade views, PDF
        rendering, logs — then fixes for everything found
  - [x] Fixed `/admin/audit-logs` **500**: `AuditLogController` used the removed
        `$this->middleware()` constructor call; the route now carries
        `permission:manage-company` alone (previously it silently demanded
        `manage-payslips` **and** `manage-company`)
  - [x] Smoke coverage: every admin GET page returns 200; salary-adjustment save
        (dashboard + staff edit), validation, gating, per-period history and the
        payslip PDF download/sync tested; removed payslip routes 404; staff portal
        payslip page lists only the signed-in staff member's own periods and PDF
        downloads are owner-only (others 404); the staff list is built from the
        saved salary adjustments so it always matches the admin's save; the Remove
        button deletes only the staff member's own payslip, the removal persists
        (the list is not rebuilt on view — `Payslip::scopeWithSavedAdjustment`),
        and HR re-saving that period regenerates the payslip
  - [x] Verification green: 197 tests / 654 assertions, Pint clean repo-wide,
        `view:cache` OK, all 27 migrations ran, no new errors in `laravel.log`

---

## Architecture

- **Framework:** Laravel 13, server-rendered Blade + Livewire components.
- **Auth/roles:** Laravel Breeze + `spatie/laravel-permission`.
  Roles: `staff`, `contract`, `part-time`, `hr`, `admin`. Contract and part-time
  are staff-level roles (clock in/out, apply for leave) selectable from the staff
  Role dropdown alongside Staff/HR/Admin.
- **Timezone:** `config('app.timezone') = 'Asia/Kuala_Lumpur'` (via `APP_TIMEZONE`).
  All application dates/times are MYT; "attendance day" boundaries are MYT.
- **Realtime dashboard:** Alpine + `fetch` polling (~10s) against a JSON
  endpoint; optional upgrade to Laravel Reverb / WebSockets later.
- **Charts:** Chart.js (npm, tree-shaken) — the admin dashboard renders today's
  attendance breakdown as a doughnut via the `attendanceChart` Alpine component.
- **Attendance status:** `App\Support\AttendanceStatus` derives
  on-time / late / absent from the clock-in time vs the earliest operational
  `start_time` plus `config('hr.late_grace_minutes')` (default 5). If no
  operational shift is configured it falls back to `config('hr.late_clock_in')`
  (default `09:00`) so statuses stay meaningful. A day with no clock-in/out at
  all (e.g. a future day) reports `no_record` instead of absent.
  `App\Support\AttendanceBreakdown::forDate()` aggregates counts for the chart
  (on time / late / absent / no record).
- **Company logo:** `App\Models\CompanySetting::logoUrl()` resolves the uploaded
  logo (or `public/images/company-logo.*` fallback); rendered everywhere via the
  `<x-company-logo>` Blade component. Admin sidebar also uses it.
- **PDF payslips:** `barryvdh/laravel-dompdf` (company-branded payslip PDF,
  `resources/views/pdf/payslip.blade.php`).
- **File storage:** local disk (profile photos, clock-in/out photos, company
  logo, leave attachments).

---

## Data Model

### `users`
- id
- employee_id (staff id, unique)
- name, email, phone, password
- **password_plain** — `Crypt`-encrypted copy of the current password so admin/HR
  can read it on the edit form (null when unknown, e.g. legacy hashes);
  `User` hashes `password` and writes this copy automatically on every password
  set (staff create/edit, profile, own-password change, email reset, registration)
- password policy for every **new** password: **min 7 characters + uppercase +
  lowercase + symbol** (`Password::defaults()` in `AppServiceProvider`)
- role (staff | contract | part-time | hr | admin)
- profile_photo_path
- **face_descriptor (json)** — enrolled face vector
- department, position, join_date
- pay basis (monthly | hourly | both)
- status (active | inactive)

### `company_settings` (single row)
- name, address, phone, email, logo_path, brand_color, latitude, longitude
- address, phone, email, latitude and longitude are required; at least one
  operational time is required (SSM Number removed)

### `attendance`
- id, user_id
- work_date
- clock_in_at, clock_in_photo_path, clock_in_face_score, clock_in_ip
- clock_out_at, clock_out_photo_path, clock_out_face_score, clock_out_ip
- worked_minutes
- status (open | closed | auto_closed) — punch lifecycle only.
  The **displayed** status (on-time / late / absent) is derived at render time
  from `clock_in_at` vs the operational shift start + grace, so it is not stored.

### `leave_types`
- id, name (annual | medical | emergency | bereavement)
- default_days, is_paid, requires_attachment

### `leave_balances`
- id, user_id, leave_type_id, year, entitlement, used, carried_over
- Per staff / leave type / **year**. Annual leave carries up to 3 unused days
  into the next year (`LeaveRollover::ANNUAL_CARRY_OVER_LIMIT`); medical,
  emergency and bereavement renew to the company default each year. When a
  non-annual type is exhausted, the surplus is drawn from annual leave.

### `leave_requests`
- id, user_id, leave_type_id
- start_date, end_date, days, reason
- attachment_path
- status (pending | approved | rejected)
- approved_by, decided_at, rejection_reason

### `payslips`
- Payroll record kept in sync with each `SalaryAdjustment` (via
  `App\Support\PayslipSync`) and the source of the downloadable company-branded
  payslip PDF (`basic = rate × hours × days`)
- id, user_id, year, month
- hourly_rate, hours_of_work, days_of_work
- basic, allowances, other_deductions, net
- generated_by, status

### `payslip_items` (optional line items)
- id, payslip_id, label, type (earning | deduction), amount

### `audit_logs`
- who changed what, when (staff/settings/payslip/leave mutations)

---

## Facial Recognition

`@vladmandic/face-api` runs in the browser. A staff member's 128-d face
descriptor is captured from a webcam during enrollment and stored on the user
record. At clock-in/out the kiosk computes a fresh descriptor and the server
compares it (euclidean distance) against the stored one using the threshold in
`config/hr.php` (`FACE_MATCH_THRESHOLD`, default 0.6). The distance is stored
on each attendance row for audit. Models are bundled under `public/models`.

This is **not** anti-spoofing; add a liveness step later if needed.

---

## Build Sequence

### Phase 0 — Scaffold
- Install Laravel, Breeze auth
- Roles/permissions (spatie)
- MYT timezone config
- Company settings module
- Base layouts (staff portal shell, admin shell)

Seeded default accounts (all password `password`):
- Admin — `admin@acap-lado.test` / `ADMIN001`
- HR — `hr@acap-lado.test` / `HR001`
- Staff — `staff@acap-lado.test` / `STAFF001`

Change these passwords before any real use. Public registration is still
enabled from Breeze and should be disabled so staff accounts are created only
by admin/HR.

### Phase 1 — Core Attendance (MVP) — DONE
- [x] Login by staff id **or** email
- [x] Admin staff CRUD + webcam face enrollment (`/admin/staff`)
- [x] Shared clock-in/out kiosk (`/attendance`): staff id + password +
      webcam snapshot + automated face verification
- [x] Attendance records with snapshot photos + match score + IP and
      auto-computed `worked_minutes`
- [x] Realtime day / week / month attendance views (`/admin/attendance`, 10s polling):
      Day = per-staff roster (incl. absent/no record); Week & Month = every
      individual clock-in/out record for the range
- [x] Per-staff filter on the attendance view (all modes) via a staff dropdown
- [x] Snapshot photos stored on the `public` disk (`storage/app/public`)

### Phase 2 — Leave — DONE
- [x] Leave types + per-staff balances
- [x] Apply flow for staff (`/staff/leaves`)
- [x] HR/Admin approve/reject (`/admin/leave-requests`)
- [x] Staff portal view of entitlements

### Phase 3 — Payroll — DONE
- [x] Hours → gross aggregation (`rate × hours × days` from SalaryAdjustment)
- [x] Salary adjustment per staff/period edited on the staff edit page **and** in
      the admin dashboard "Salary & Payslips" section (manual month/year picker);
      saving recalculates that period's payroll record automatically
- [x] Payslip lines keep allowances + other deductions on refresh; statutory
      EPF/SOCSO/EIS/PCB deliberately removed — `net = basic + allowances − other`
- [x] Downloadable payslip PDF per employee/period (company info + salary adjustment)

### Phase 4 — Polish
- [x] Audit logs (`/admin/audit-logs`, `manage-company` only)
- [x] Notifications (leave requested / decided) with per-notification **Remove**
      and **Clear all** history actions on `/notifications`
- [x] Reports / exports (attendance, leave requests)
- [ ] Remaining polish items

---

## Admin Dashboard Features
- Real-time staff clock-in/out for a day, week or month
  (Week/Month list every individual record with date + status)
- Today's attendance breakdown doughnut chart (on-time / late / absent / no record)
- **Salary & Payslips** section: per-employee rate (RM/hour) × total hours × total
  days with a live basic-pay total, manual month/year period picker, per-row Save
  (auto-generates and syncs the payslip) and a downloadable company-branded
  payslip PDF per employee/period
- Leave-request status doughnut chart (pending / approved / rejected) plus a
  Pending Approvals list with inline Approve / Reject actions right on the dashboard
- Admin can add/edit **remarks** on any leave request (pending/approved/rejected)
  from the dashboard and the Leave Requests page (`remarks` column on `leave_requests`)
- Attendance status per staff = on-time / late / absent (operational start + grace)
- Edit company info (name, address, phone, email, **logo**, **brand colour**) — all
  fields required, incl. at least one operational shift (SSM Number removed)
- Update/delete staff members
- Change a staff password from the edit form: **no current password needed** — an
  admin/HR user with `manage-staff` can reset any employee's password directly
  (New Password, leave blank to keep); the field has a show/hide (eye) toggle
  (`<x-password-input>`) — department/position removed. Staff changing their *own*
  password on the Profile page still must enter their current password.
- Edit form shows the employee's **Current Password read-only**, synced from the
  encrypted `users.password_plain` copy ("Not available" when no copy exists);
  New Password field carries the policy hint (min 7, upper/lower/symbol)
- Upload/replace each staff's profile photo (from the staff list or the edit form)
- View each staff's total leave balances (per staff, per leave type) with used,
  remaining and carried over
- Salary adjustment per staff on the **staff edit page**: period picker (month/year),
  rate × hours × days with a live basic-pay preview, and the saved-period history;
  saving recalculates that period's payroll record
- Add/edit/delete staff info (staff id, email, phone, profile picture, password)

## Staff Portal Features
- Login and view leave entitlements (annual, medical, emergency, bereavement)
- Read-only profile card on the dashboard: profile picture (initials fallback when
  none is set), company name, staff name and staff ID — view only, with no
  upload/edit/delete affordance (photo, name and staff ID are maintained by HR)
- Apply for leave (`/staff/leaves`); view approved/rejected/pending status and
  cancel a pending request
- View and download **My Payslips** (`/staff/payslips`) — derived from the admin's
  saved Salary & Payslips adjustments, so each period shows and downloads the same
  figures HR saved (owner-only; PDF refreshed from the adjustment on download)
- Each payslip row has a **Remove** button that deletes the payslip record; the
  removal sticks until HR saves that period again, which regenerates it
- Clock-in/out via staff id + password + webcam snapshot + face verify

---

## Open Items (decide before/during build)

1. **Attendance rule** — auto-close open clock-ins at start of the next MYT
   day, or require an explicit clock-out? (Currently records stay `open` until
   the staff clocks out.)
2. **Statutory tables** — auto-compute EPF/SOCSO from official MY tables, or
   just default editable fields?
3. **Liveness check** — face matching is not anti-spoofing; add a liveness step
   if required.
4. **Camera restrictions** — should clock-in be allowed only from specific
   IPs/station, or anywhere?
