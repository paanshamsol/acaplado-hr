/**
 * Shared, reactive leave-request counters so every part of the dashboard
 * (doughnut chart, stat chips, pending badge) updates together the moment a
 * request is approved, rejected or remarked — without a page reload.
 */
export function registerLeaveStores(Alpine) {
    Alpine.store('leaveCounts', { pending: 0, approved: 0, rejected: 0 });
}

function postForm(form) {
    const csrf = document.querySelector('meta[name="csrf-token"]');

    return fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrf ? csrf.content : '',
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        body: new FormData(form),
    });
}

/**
 * Wrapper around the dashboard's "Pending Approvals" panel: keeps the counters
 * in sync and shows a toast after every decision.
 */
export function pendingApprovals({
    pending = 0,
    approved = 0,
    rejected = 0,
    summaryText = '{count} request(s) awaiting your decision.',
    summaryEmpty = 'No requests awaiting your decision.',
} = {}) {
    return {
        toast: { show: false, message: '', tone: 'success' },
        timer: null,

        init() {
            this.$store.leaveCounts.pending = pending;
            this.$store.leaveCounts.approved = approved;
            this.$store.leaveCounts.rejected = rejected;
        },

        onDecided(detail) {
            if (detail.counts) {
                Object.assign(this.$store.leaveCounts, detail.counts);
            }

            if (detail.status === 'leave-already-decided') {
                this.showToast(detail.message, 'warning');
                setTimeout(() => window.location.reload(), 1200);

                return;
            }

            const tone = detail.status === 'leave-rejected'
                ? 'danger'
                : (detail.status === 'error' ? 'warning' : 'success');

            this.showToast(detail.message, tone);
        },

        onRemarksSaved(detail) {
            if (detail.counts) {
                Object.assign(this.$store.leaveCounts, detail.counts);
            }

            this.showToast(detail.message || 'Remarks saved.', 'success');
        },

        pendingSummary() {
            const count = this.$store.leaveCounts.pending;

            return count === 0
                ? summaryEmpty
                : summaryText.replace('{count}', count);
        },

        showToast(message, tone = 'success') {
            clearTimeout(this.timer);

            this.toast = { show: true, message, tone };
            this.timer = setTimeout(() => {
                this.toast.show = false;
            }, 4000);
        },

        toastClass() {
            return {
                success: 'border-green-200 bg-green-50 text-green-800',
                danger: 'border-red-200 bg-red-50 text-red-800',
                warning: 'border-amber-200 bg-amber-50 text-amber-800',
            }[this.toast.tone] ?? 'border-gray-200 bg-white text-gray-800';
        },
    };
}

/**
 * A single pending request card: expandable details, inline remarks editing
 * and approve/reject submitted over fetch (the plain forms still work when
 * JavaScript is unavailable).
 */
export function approvalItem(payload = {}) {
    return {
        id: payload.id ?? null,
        remarks: payload.remarks ?? '',
        busy: false,
        decided: false,
        expanded: false,
        rejecting: false,
        editingRemarks: false,

        async submit(event) {
            event.preventDefault();

            if (this.busy) {
                return;
            }

            const form = event.target;

            this.busy = true;

            try {
                const response = await postForm(form);
                const data = await response.json().catch(() => null);

                if (!response.ok || !data) {
                    if (data && data.errors) {
                        window.dispatchEvent(new CustomEvent('leave-decided', {
                            detail: {
                                status: 'error',
                                message: Object.values(data.errors).flat()[0] || 'Unable to save.',
                                counts: null,
                            },
                        }));

                        return;
                    }

                    this.nativeSubmit(form);

                    return;
                }

                if (data.status === 'leave-remarks-saved') {
                    this.remarks = data.remarks ?? '';
                    this.editingRemarks = false;
                } else {
                    this.decided = true;
                }

                window.dispatchEvent(new CustomEvent('leave-decided', { detail: data }));
            } catch (error) {
                this.nativeSubmit(form);
            } finally {
                this.busy = false;
            }
        },

        nativeSubmit(form) {
            HTMLFormElement.prototype.submit.call(form);
        },
    };
}
