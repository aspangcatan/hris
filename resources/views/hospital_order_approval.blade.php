@extends('master')

@section('content')
    @php
        $canApproveHospitalOrder = in_array(session('access_rights'), ['admin', 'calendar']);
    @endphp

    @if(!$canApproveHospitalOrder)
        <div class="alert alert-danger">You are not authorized to approve/reject filings.</div>
    @else
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Approve / Reject Hospital Order Filings</h4>
                    <a href="{{ route('hospital_order') }}" class="btn btn-sm btn-secondary">Back</a>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Time Range</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody id="review_list">
                        <tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@stop

@section('js')
<script>
    $(document).ready(function () {
        getFilingsForReview();
    });

    function getFilingsForReview() {
        fetch("{{ route('hospital-orders.filings-for-review') }}")
            .then(res => res.json())
            .then(data => {
                $('#review_list').empty();

                if (!data || data.length === 0) {
                    $('#review_list').html('<tr><td colspan="7" class="text-center text-muted">No filings for review.</td></tr>');
                    return;
                }

                data.forEach(it => {
                    const canReview = (it.status || '').toLowerCase() === 'pending';
                    $('#review_list').append(`
                        <tr>
                            <td>${escapeHtml((it.employee_name || '').replace(/\s+/g, ' ').trim())}</td>
                            <td>#${escapeHtml(it.order_number || '')}</td>
                            <td>${formatDate(it.filed_date)}</td>
                            <td>${formatTimeRange(it.time_from, it.time_to)}</td>
                            <td>${statusBadge(it.status)}</td>
                            <td>${escapeHtml(it.remarks || '-')}</td>
                            <td>
                                ${canReview ? `
                                    <button class="btn btn-success btn-sm mb-1" onclick="reviewFiling(${it.id}, 'approve')">Approve</button>
                                    <button class="btn btn-danger btn-sm" onclick="reviewFiling(${it.id}, 'reject')">Reject</button>
                                ` : '-'}
                            </td>
                        </tr>
                    `);
                });
            })
            .catch(() => $('#review_list').html('<tr><td colspan="7" class="text-center text-danger">Failed to load.</td></tr>'));
    }

    function reviewFiling(id, action) {
        const endpoint = action === 'approve'
            ? `{{ url('/hospital-orders/filings') }}/${id}/approve`
            : `{{ url('/hospital-orders/filings') }}/${id}/reject`;

        const remarks = prompt('Remarks (optional):') || '';

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({remarks: remarks})
        })
            .then(res => res.json())
            .then(data => {
                if (data.error || data.errors) throw data;
                toastr.success(data.message || 'Updated.', 'Success');
                getFilingsForReview();
            })
            .catch(err => showApiError(err, 'Failed to update status.'));
    }

    function formatDate(v) {
        if (!v) return '-';
        return moment(v, 'YYYY-MM-DD').format('MMM D, YYYY');
    }

    function formatTimeRange(from, to) {
        if (!from || !to) return '-';
        return `${moment(from, 'HH:mm:ss').format('hh:mm A')} - ${moment(to, 'HH:mm:ss').format('hh:mm A')}`;
    }

    function statusBadge(status) {
        const s = (status || 'pending').toLowerCase();
        let cls = 'secondary';
        if (s === 'approved') cls = 'success';
        if (s === 'rejected') cls = 'danger';
        if (s === 'pending') cls = 'warning text-dark';
        return `<span class="badge bg-${cls} text-uppercase">${escapeHtml(s)}</span>`;
    }

    function showApiError(errorPayload, fallbackMessage) {
        if (errorPayload && errorPayload.errors) {
            const firstKey = Object.keys(errorPayload.errors)[0];
            const firstError = firstKey ? errorPayload.errors[firstKey][0] : fallbackMessage;
            toastr.error(firstError, 'Error');
            return;
        }
        if (errorPayload && errorPayload.error) {
            toastr.error(errorPayload.error, 'Error');
            return;
        }
        toastr.error(fallbackMessage, 'Error');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
</script>
@stop
