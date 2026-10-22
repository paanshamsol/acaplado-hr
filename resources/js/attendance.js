/**
 * Alpine component driving the realtime attendance dashboard.
 */
/**
 * Latitude / longitude editing with a live Google Maps preview. Uses the no-key
 * embed URL so no API key is required.
 */
export function companyLocationMap({ latitude = null, longitude = null } = {}) {
    return {
        latitude,
        longitude,
        locationError: '',
        get mapSrc() {
            const lat = String(this.latitude ?? '').trim();
            const lng = String(this.longitude ?? '').trim();

            if (lat === '' || lng === '') {
                return '';
            }

            return `https://maps.google.com/maps?q=${encodeURIComponent(`${lat},${lng}`)}&z=16&output=embed`;
        },
        useCurrentLocation() {
            this.locationError = '';

            if (!navigator.geolocation) {
                this.locationError = 'Geolocation is not available in this browser. Type the coordinates instead.';
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.latitude = Number(position.coords.latitude.toFixed(7));
                    this.longitude = Number(position.coords.longitude.toFixed(7));
                },
                () => {
                    this.locationError = 'Unable to read your location. Check the browser location permission.';
                },
            );
        },
    };
}

/**
 * Editable rows for the company operational times. Existing rows carry their
 * id; newly added rows have null id and are inserted on save.
 */
export function operationalTimesForm(initial = [], dirty = false) {
    return {
        rows: (initial || []).map((row) => ({
            id: row.id ?? null,
            label: row.label ?? '',
            start_time: row.start_time ?? '',
            end_time: row.end_time ?? '',
        })),
        dirty,
        addRow() {
            this.rows.push({ id: null, label: '', start_time: '', end_time: '' });
            this.dirty = true;
        },
        removeRow(index) {
            this.rows.splice(index, 1);
            this.dirty = true;
        },
    };
}

/**
 * Company logo picker: previews the newly selected file and lets the admin
 * clear the current logo.
 */
export function companyLogo({ url = null } = {}) {
    return {
        preview: url,
        remove: false,
        onSelect(event) {
            const file = event.target.files?.[0];

            if (file) {
                this.preview = URL.createObjectURL(file);
                this.remove = false;
            }
        },

        toggleStaff(userId) {
            if (this.expandedStaff.includes(userId)) {
                this.expandedStaff = this.expandedStaff.filter((id) => id !== userId);

                return;
            }

            this.expandedStaff.push(userId);
        },

        isExpanded(userId) {
            return this.expandedStaff.includes(userId);
        },
    };
}

export function attendanceBoard({
    dataUrl,
    today = '',
    week = '',
    month = '',
    year = '',
    staffList = [],
    storeUrl = '',
    updateUrl = '',
    deleteUrl = '',
    canManage = false,
}) {
    return {
        mode: 'summary',
        date: '',
        week: '',
        month: '',
        year: '',
        staff: '',
        staffRange: 'month',
        fromDate: '',
        toDate: '',
        search: '',
        defaults: { date: '', week: '', month: '', year: '' },
        staffList,
        storeUrl,
        updateUrl,
        deleteUrl,
        canManage,
        rows: [],
        grouped: [],
        summary: [],
        expandedStaff: [],
        stats: {},
        label: '',
        generatedAt: '',
        loading: false,
        timer: null,
        formOpen: false,
        saving: false,
        deleting: false,
        editingId: null,
        formError: '',
        form: {
            user_id: '',
            work_date: '',
            clock_in_time: '',
            clock_out_time: '',
        },

        async init() {
            const localNow = today || new Date().toLocaleDateString('en-CA');
            const currentYear = year || localNow.slice(0, 4);

            this.date = localNow;
            this.week = week;
            this.month = month || localNow.slice(0, 7);
            this.year = currentYear;
            this.fromDate = localNow.slice(0, 8) + '01';
            this.toDate = localNow;

            this.defaults = { date: this.date, week: this.week, month: this.month, year: this.year };

            await this.fetchData();

            this.timer = setInterval(() => this.fetchData(), 10000);

            this.$watch('mode', () => this.fetchData());
            this.$watch('staff', () => {
                if (['staff', 'week', 'month', 'year', 'summary'].includes(this.mode)) {
                    this.fetchData();
                }
            });
            this.$watch('staffRange', () => {
                if (this.mode === 'staff' || this.mode === 'summary') this.fetchData();
            });
            this.$watch('date', () => {
                if (this.mode === 'day') this.fetchData();
            });
            this.$watch('week', () => {
                if (['week', 'staff', 'summary'].includes(this.mode) && this.staffRange === 'week') this.fetchData();
            });
            this.$watch('month', () => {
                if (['month', 'staff', 'summary'].includes(this.mode) && (this.mode === 'month' || this.staffRange === 'month')) this.fetchData();
            });
            this.$watch('year', () => {
                if (['year', 'staff', 'summary'].includes(this.mode) && this.staffRange === 'year') this.fetchData();
            });
            this.$watch('fromDate', () => {
                if (['staff', 'summary'].includes(this.mode) && this.staffRange === 'custom') this.fetchData();
            });
            this.$watch('toDate', () => {
                if (['staff', 'summary'].includes(this.mode) && this.staffRange === 'custom') this.fetchData();
            });
        },

        get hasSearch() {
            return this.search.trim() !== '';
        },

        get filteredRows() {
            const query = this.search.trim().toLowerCase();

            if (query === '') {
                return this.rows;
            }

            return this.rows.filter((row) => [row.name, row.employee_id, row.date, row.status, row.label, row.work_date, row.weekday]
                .some((value) => String(value ?? '').toLowerCase().includes(query)));
        },

        get filteredSummary() {
            const query = this.search.trim().toLowerCase();

            if (query === '') {
                return this.summary;
            }

            return this.summary.filter((member) => [
                member.name,
                member.employee_id,
                member.department,
                ...(member.dates ?? []).flatMap((entry) => [entry.date, entry.weekday, entry.status, entry.clock_in, entry.clock_out]),
            ].some((value) => String(value ?? '').toLowerCase().includes(query)));
        },

        setMode(next) {
            if (next === 'staff' && !this.staff && this.staffList.length > 0) {
                this.staff = String(this.staffList[0].id);
            }

            this.mode = next;
        },

        setRange(range) {
            if (!['staff', 'summary'].includes(this.mode)) {
                this.mode = 'summary';
            }

            this.staffRange = range;
        },

        expandAll() {
            this.expandedStaff = this.summary.map((member) => member.user_id);
        },

        collapseAll() {
            this.expandedStaff = [];
        },

        resetFilters() {
            this.search = '';
            this.staff = '';
            this.mode = 'summary';
            this.staffRange = 'month';
            const localNow = today || new Date().toLocaleDateString('en-CA');
            this.date = this.defaults.date || localNow;
            this.week = this.defaults.week || '';
            this.month = this.defaults.month || localNow.slice(0, 7);
            this.year = this.defaults.year || localNow.slice(0, 4);
            this.fromDate = localNow.slice(0, 8) + '01';
            this.toDate = localNow;
        },

        async fetchData() {
            this.loading = true;

            try {
                const params = new URLSearchParams({ mode: this.mode });

                if (this.mode === 'week') {
                    params.set('week', this.week);
                    if (this.staff) params.set('staff', this.staff);
                } else if (this.mode === 'month') {
                    params.set('month', this.month);
                    if (this.staff) params.set('staff', this.staff);
                } else if (this.mode === 'year') {
                    params.set('year', this.year);
                    if (this.staff) params.set('staff', this.staff);
                } else if (this.mode === 'staff') {
                    if (this.staff) {
                        params.set('staff', this.staff);
                    }
                    params.set('staff_range', this.staffRange);
                    if (this.staffRange === 'week') params.set('week', this.week);
                    else if (this.staffRange === 'month') params.set('month', this.month);
                    else if (this.staffRange === 'year') params.set('year', this.year);
                    else if (this.staffRange === 'custom') {
                        if (this.fromDate) params.set('from_date', this.fromDate);
                        if (this.toDate) params.set('to_date', this.toDate);
                    }
                } else if (this.mode === 'summary') {
                    if (this.staff) {
                        params.set('staff', this.staff);
                    }
                    params.set('staff_range', this.staffRange);
                    if (this.staffRange === 'week') params.set('week', this.week);
                    else if (this.staffRange === 'month') params.set('month', this.month);
                    else if (this.staffRange === 'year') params.set('year', this.year);
                    else if (this.staffRange === 'custom') {
                        if (this.fromDate) params.set('from_date', this.fromDate);
                        if (this.toDate) params.set('to_date', this.toDate);
                    }
                } else {
                    params.set('date', this.date);
                    if (this.staff) params.set('staff', this.staff);
                }

                const response = await fetch(`${dataUrl}?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                });

                const data = await response.json();

                this.rows = data.rows ?? [];
                this.grouped = data.grouped ?? [];
                this.summary = data.summary ?? [];
                this.stats = data.stats ?? {};
                this.label = data.label ?? '';
                this.generatedAt = data.generated_at ?? '';
            } catch (e) {
                // keep the previous data on a transient error
            } finally {
                this.loading = false;
            }
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        },

        openCreate(date = null, userId = '') {
            this.editingId = null;
            this.formError = '';
            this.form = {
                user_id: userId || this.staff || '',
                work_date: date || this.date || this.defaults.date || '',
                clock_in_time: '',
                clock_out_time: '',
            };
            this.formOpen = true;
        },

        openEdit(row) {
            this.editingId = row.id;
            this.formError = '';
            this.form = {
                user_id: row.user_id ?? '',
                work_date: row.work_date || '',
                clock_in_time: row.clock_in_time || '',
                clock_out_time: row.clock_out_time || '',
            };
            this.formOpen = true;
        },

        closeForm() {
            if (this.saving) {
                return;
            }

            this.formOpen = false;
            this.formError = '';
        },

        async saveForm() {
            this.saving = true;
            this.formError = '';

            const editing = this.editingId !== null;
            const url = editing
                ? this.updateUrl.replace('__ID__', this.editingId)
                : this.storeUrl;

            try {
                const response = await fetch(url, {
                    method: editing ? 'PUT' : 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify(this.form),
                });

                const data = await response.json();

                if (!response.ok) {
                    const errors = Object.values(data.errors ?? {});
                    this.formError = errors.length > 0 ? errors[0][0] : (data.message || 'Unable to save the record.');

                    return;
                }

                this.formOpen = false;
                await this.fetchData();
            } catch (error) {
                this.formError = 'Unable to save the record.';
            } finally {
                this.saving = false;
            }
        },

        async deleteRow(row = null) {
            const id = row?.id ?? this.editingId;

            if (!id || !window.confirm('Delete this attendance record? This cannot be undone.')) {
                return;
            }

            this.deleting = true;
            this.formError = '';

            try {
                const response = await fetch(this.deleteUrl.replace('__ID__', id), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                });

                const data = await response.json();

                if (!response.ok) {
                    this.showDeleteError(data.message || 'Unable to delete the record.');

                    return;
                }

                this.formOpen = false;
                await this.fetchData();
            } catch (error) {
                this.showDeleteError('Unable to delete the record.');
            } finally {
                this.deleting = false;
            }
        },

        showDeleteError(message) {
            if (this.formOpen) {
                this.formError = message;

                return;
            }

            window.alert(message);
        },

        statusClass(status) {
            return {
                on_time: 'bg-green-100 text-green-700',
                late: 'bg-red-100 text-red-700',
                absent: 'bg-blue-100 text-blue-700',
                no_record: 'bg-gray-100 text-gray-500',
                open: 'bg-blue-100 text-blue-700',
                closed: 'bg-green-100 text-green-700',
            }[status] ?? 'bg-gray-100 text-gray-500';
        },

        statusLabel(status) {
            return {
                on_time: 'On time',
                late: 'Late',
                absent: 'Absent',
                no_record: 'No record',
                open: 'Open',
                closed: 'Closed',
            }[status] ?? status;
        },
    };
}
