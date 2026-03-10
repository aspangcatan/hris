@extends('master')

@section('content')
    @php
        $canApproveHospitalOrder = in_array(session('access_rights'), ['admin'], true) || session('is_section_head') || session('is_division_head');
    @endphp

    @if(!$canApproveHospitalOrder)
        <div class="alert alert-danger">You are not authorized to approve/reject filings.</div>
    @else
        <style>
            .ho-approval-page {
                --ho-surface: #ffffff;
                --ho-line: #dce6f2;
                --ho-muted: #5e728d;
                --ho-title: #10233d;
                --ho-grad-1: #0f766e;
                --ho-grad-2: #0ea5a4;
                --ho-grad-3: #0891b2;
            }

            .ho-approval-shell {
                border: 0;
                border-radius: 20px;
                box-shadow: 0 24px 44px rgba(15, 23, 42, 0.10);
                background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
                overflow: hidden;
            }

            .ho-approval-header {
                background: linear-gradient(125deg, var(--ho-grad-1), var(--ho-grad-2), var(--ho-grad-3));
                color: #fff;
                padding: 1.1rem 1.2rem;
            }

            .ho-approval-title {
                margin: 0;
                font-weight: 800;
                letter-spacing: 0.2px;
            }

            .ho-approval-subtitle {
                margin: .2rem 0 0;
                opacity: .95;
                font-weight: 500;
                font-size: .9rem;
            }

            .ho-approval-table-wrap {
                border: 1px solid var(--ho-line);
                border-radius: 14px;
                overflow: hidden;
                background: #fff;
            }

            .ho-approval-table {
                margin-bottom: 0;
            }

            .ho-approval-table thead th {
                background: #f5f9ff;
                color: #4f6684;
                text-transform: uppercase;
                letter-spacing: .06em;
                font-size: .72rem;
                font-weight: 800;
            }

            .ho-approval-table tbody tr:hover {
                background: #f8fbff;
            }

            .ho-empty {
                text-align: center;
                color: #6f829a;
                padding: 1rem .5rem !important;
            }

            .ho-pagination .page-link {
                border-radius: 10px;
                margin: 0 2px;
            }

            .ho-chip {
                display: inline-block;
                border-radius: 999px;
                padding: .2rem .55rem;
                background: #edf4ff;
                color: #355379;
                font-weight: 600;
                font-size: .8rem;
            }

            .ho-approval-search {
                max-width: 340px;
            }
        </style>

        <div class="ho-approval-page">
            <div class="card ho-approval-shell">
                <div class="ho-approval-header d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <h4 class="ho-approval-title">Approve / Reject Hospital Order Filings</h4>
                        <p class="ho-approval-subtitle">Review submitted filings and provide optional remarks</p>
                    </div>
                    <a href="{{ route('hospital_order') }}" class="btn btn-light btn-sm">Back</a>
                </div>

                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
                        <h6 class="mb-0 fw-bold">Filings for Review</h6>
                        <input type="text" id="reviewSearch" class="form-control ho-approval-search" placeholder="Search employee/order #/status...">
                    </div>

                    <div class="ho-approval-table-wrap">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle ho-approval-table">
                                <thead>
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
                                <tr><td colspan="7" class="ho-empty">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                        <small class="text-muted" id="reviewSummary"></small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0 ho-pagination" id="reviewPagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    @endif
@stop

@section('js')
<script>
    let allFilingsForReview = [];
    let filteredFilingsForReview = [];
    let currentPage = 1;
    const pageSize = 8;

    $(document).ready(function () {
        getFilingsForReview();

        $('#reviewSearch').on('input', function () {
            currentPage = 1;
            applyReviewFilter();
        });
    });

    function getFilingsForReview() {
        fetch("{{ route('hospital-orders.filings-for-review') }}")
            .then(res => res.json())
            .then(data => {
                allFilingsForReview = data || [];
                currentPage = 1;
                applyReviewFilter();
            })
            .catch(() => {
                $('#review_list').html('<tr><td colspan="7" class="ho-empty text-danger">Failed to load.</td></tr>');
                $('#reviewPagination').empty();
                $('#reviewSummary').text('');
            });
    }

    function applyReviewFilter() {
        const keyword = ($('#reviewSearch').val() || '').trim().toLowerCase();

        filteredFilingsForReview = allFilingsForReview.filter(it => {
            const employee = String((it.employee_name || '').replace(/\s+/g, ' ').trim()).toLowerCase();
            const orderNo = String(it.order_number || '').toLowerCase();
            const status = String(it.status || '').toLowerCase();
            return employee.includes(keyword) || orderNo.includes(keyword) || status.includes(keyword);
        });

        renderReviewRows();
        renderReviewPagination();
    }

    function renderReviewRows() {
        const $list = $('#review_list');
        $list.empty();

        if (!filteredFilingsForReview.length) {
            $list.html('<tr><td colspan="7" class="ho-empty">No filings for review.</td></tr>');
            $('#reviewSummary').text('0 results');
            return;
        }

        const total = filteredFilingsForReview.length;
        const totalPages = Math.ceil(total / pageSize);
        if (currentPage > totalPages) currentPage = totalPages;

        const startIndex = (currentPage - 1) * pageSize;
        const pageItems = filteredFilingsForReview.slice(startIndex, startIndex + pageSize);

        pageItems.forEach(it => {
            const canReview = String(it.status || '').toLowerCase() === 'pending';
            $list.append(`
                <tr>
                    <td>${escapeHtml((it.employee_name || '').replace(/\s+/g, ' ').trim())}</td>
                    <td><span class="ho-chip">#${escapeHtml(it.order_number || '')}</span></td>
                    <td>${formatDate(it.filed_date)}</td>
                    <td>${formatTimeRange(it.time_from, it.time_to)}</td>
                    <td>${statusBadge(it.status)}</td>
                    <td>${escapeHtml(it.remarks || '-')}</td>
                    <td>
                        ${canReview ? `
                            <button type="button"  class="btn btn-action btn-edit me-1 mb-1" onclick="reviewFiling(${it.id}, 'approve')" title="Approve"><i class="bi bi-check2"></i></button>
                            <button type="button"  class="btn btn-action btn-delete mb-1" onclick="reviewFiling(${it.id}, 'reject')" title="Reject"><i class="bi bi-x"></i></button>
                        ` : '-'}
                    </td>
                </tr>
            `);
        });

        const from = startIndex + 1;
        const to = Math.min(startIndex + pageItems.length, total);
        $('#reviewSummary').text(`Showing ${from}-${to} of ${total} result(s)`);
    }

    function renderReviewPagination() {
        const $pagination = $('#reviewPagination');
        $pagination.empty();

        const totalPages = Math.ceil(filteredFilingsForReview.length / pageSize);
        if (totalPages <= 1) return;

        const prevDisabled = currentPage === 1 ? 'disabled' : '';
        $pagination.append(`
            <li class="page-item ${prevDisabled}">
                <a class="page-link" href="#" data-page="prev">Prev</a>
            </li>
        `);

        for (let i = 1; i <= totalPages; i++) {
            const active = i === currentPage ? 'active' : '';
            $pagination.append(`
                <li class="page-item ${active}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        const nextDisabled = currentPage === totalPages ? 'disabled' : '';
        $pagination.append(`
            <li class="page-item ${nextDisabled}">
                <a class="page-link" href="#" data-page="next">Next</a>
            </li>
        `);

        $pagination.find('a').on('click', function (e) {
            e.preventDefault();
            const page = $(this).data('page');

            if (page === 'prev' && currentPage > 1) currentPage--;
            if (page === 'next' && currentPage < totalPages) currentPage++;
            if (Number.isInteger(page)) currentPage = page;

            renderReviewRows();
            renderReviewPagination();
        });
    }

    function reviewFiling(id, action) {
        const endpoint = action === 'approve'
            ? `{{ url('/hospital-orders/filings') }}/${id}/approve`
            : `{{ url('/hospital-orders/filings') }}/${id}/reject`;

        const actionLabel = action === 'approve' ? 'Approve' : 'Reject';
        const actionColor = action === 'approve' ? '#198754' : '#dc3545';

        Swal.fire({
            title: `${actionLabel} Filing`,
            input: 'text',
            inputLabel: 'Remarks (optional)',
            inputPlaceholder: 'Enter remarks',
            showCancelButton: true,
            confirmButtonText: actionLabel,
            confirmButtonColor: actionColor
        }).then((result) => {
            if (!result.isConfirmed) return;

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({remarks: result.value || ''})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.error || data.errors) throw data;
                    toastr.success(data.message || 'Updated.', 'Success');
                    getFilingsForReview();
                })
                .catch(err => showApiError(err, 'Failed to update status.'));
        });
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
