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
    };
}

export function attendanceBoard({ dataUrl, today = '', week = '', month = '', staffList = [] }) {
    return {
        mode: 'day',
        date: '',
        week: '',
        month: '',
        staff: '',
        search: '',
        defaults: { date: '', week: '', month: '' },
        staffList,
        rows: [],
        stats: {},
        label: '',
        generatedAt: '',
        loading: false,
        timer: null,

        async init() {
            const localNow = today || new Date().toLocaleDateString('en-CA');

            this.date = localNow;
            this.week = week;
            this.month = month || localNow.slice(0, 7);

            this.defaults = { date: this.date, week: this.week, month: this.month };

            await this.fetchData();

            this.timer = setInterval(() => this.fetchData(), 10000);

            this.$watch('mode', () => this.fetchData());
            this.$watch('staff', () => this.fetchData());
            this.$watch('date', () => {
                if (this.mode === 'day') this.fetchData();
            });
            this.$watch('week', () => {
                if (this.mode === 'week') this.fetchData();
            });
            this.$watch('month', () => {
                if (this.mode === 'month') this.fetchData();
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

            return this.rows.filter((row) => [row.name, row.employee_id, row.date, row.status, row.label]
                .some((value) => String(value ?? '').toLowerCase().includes(query)));
        },

        resetFilters() {
            this.search = '';
            this.staff = '';
            this.mode = 'day';
            this.date = this.defaults.date;
            this.week = this.defaults.week;
            this.month = this.defaults.month;
        },

        async fetchData() {
            this.loading = true;

            try {
                const params = new URLSearchParams({ mode: this.mode });

                if (this.mode === 'week') {
                    params.set('week', this.week);
                } else if (this.mode === 'month') {
                    params.set('month', this.month);
                } else {
                    params.set('date', this.date);
                }

                if (this.staff) {
                    params.set('staff', this.staff);
                }

                const response = await fetch(`${dataUrl}?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                });

                const data = await response.json();

                this.rows = data.rows ?? [];
                this.stats = data.stats ?? {};
                this.label = data.label ?? '';
                this.generatedAt = data.generated_at ?? '';
            } catch (e) {
                // keep the previous data on a transient error
            } finally {
                this.loading = false;
            }
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
