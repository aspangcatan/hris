@extends('master')

@section('content')
    <style>
        .ot-file-page {
            --ot-surface: #ffffff;
            --ot-line: #dce6f2;
            --ot-muted: #5e728d;
            --ot-grad-1: #14532d;
            --ot-grad-2: #16a34a;
            --ot-grad-3: #22c55e;
        }

        .ot-file-shell {
            border: 0;
            border-radius: 20px;
            box-shadow: 0 24px 44px rgba(15, 23, 42, 0.10);
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
            overflow: hidden;
        }

        .ot-file-header {
            background: linear-gradient(125deg, var(--ot-grad-1), var(--ot-grad-2), var(--ot-grad-3));
            color: #fff;
            padding: 1.1rem 1.2rem;
        }

        .ot-file-title {
            margin: 0;
            font-weight: 800;
            letter-spacing: 0.2px;
        }

        .ot-file-subtitle {
            margin: .2rem 0 0;
            opacity: .95;
            font-weight: 500;
            font-size: .9rem;
        }

        .ot-file-card {
            border: 1px solid var(--ot-line);
            border-radius: 14px;
            background: var(--ot-surface);
            padding: .95rem;
        }

        .ot-file-label {
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-weight: 700;
            color: var(--ot-muted);
            margin-bottom: .32rem;
        }

        .ot-file-table-wrap {
            border: 1px solid var(--ot-line);
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
        }

        .ot-file-table {
            margin-bottom: 0;
        }

        .ot-file-table thead th {
            background: #f5f9ff;
            color: #4f6684;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-size: .72rem;
            font-weight: 800;
        }

        .ot-file-table tbody tr:hover {
            background: #f8fbff;
        }

        .ot-empty {
            text-align: center;
            color: #6f829a;
            padding: 1rem .5rem !important;
        }

        .ot-pagination .page-link {
            border-radius: 10px;
            margin: 0 2px;
        }

        .ot-file-search {
            max-width: 320px;
        }
    </style>

    <div class="ot-file-page">
        <div class="card ot-file-shell">
            <div class="ot-file-header d-flex justify-content-between align-items-center gap-2">
                <div>
                    <h4 class="ot-file-title">File Overtime Request</h4>
                    <p class="ot-file-subtitle">Submit your overtime date and time range for approval</p>
                </div>
                <a href="{{ route('dtr') }}" class="btn btn-light btn-sm">Back</a>
            </div>

            <div class="card-body p-3">
                <div class="ot-file-card mb-3">
                    <form id="fileOvertimeForm" class="row g-3 align-items-end">
                        <div class="col-lg-2">
                            <label class="ot-file-label">Date</label>
                            <input type="date" class="form-control" name="ot_date" required>
                        </div>
                        <div class="col-lg-2">
                            <label class="ot-file-label">Time From</label>
                            <input type="time" class="form-control" name="time_from" required>
                        </div>
                        <div class="col-lg-2">
                            <label class="ot-file-label">Time To</label>
                            <input type="time" class="form-control" name="time_to" required>
                        </div>
                        <div class="col-lg-5">
                            <label class="ot-file-label">Reason (optional)</label>
                            <input type="text" class="form-control" name="reason" maxlength="500">
                        </div>
                        <div class="col-lg-1">
                            <button type="submit" class="btn btn-primary w-100">File</button>
                        </div>
                    </form>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
                    <h6 class="mb-0 fw-bold">My Overtime Filings</h6>
                    <input type="text" id="filingSearch" class="form-control ot-file-search" placeholder="Search reason/status...">
                </div>

                <div class="ot-file-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle ot-file-table">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time Range</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody id="my_filing_list">
                            <tr><td colspan="6" class="ot-empty">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted" id="filingSummary"></small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0 ot-pagination" id="filingPagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
    let allFilings = [];
    let filteredFilings = [];
    let currentPage = 1;
    const pageSize = 8;
    const filingDeleteBaseUrl = "{{ url('/overtime/filings') }}";

    $(document).ready(function () {
        getMyFilings();

        $('#filingSearch').on('input', function () {
            currentPage = 1;
            applyFilingFilter();
        });

        $('body').on('click', '.btn-delete-filing', function () {
            const filingId = Number($(this).data('id'));
            if (!filingId) return;
            deleteFiling(filingId);
        });

        $('#fileOvertimeForm').submit(function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('{{ route("overtime.file") }}', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.error || data.errors) throw data;
                    toastr.success(data.message || 'Filed.', 'Success');
                    this.reset();
                    getMyFilings();
                })
                .catch(err => showApiError(err, 'Failed to submit overtime filing.'));
        });
    });

    function getMyFilings() {
        fetch("{{ route('overtime.my-filings') }}")
            .then(res => res.json())
            .then(data => {
                allFilings = data || [];
                currentPage = 1;
                applyFilingFilter();
            })
            .catch(() => {
                $('#my_filing_list').html('<tr><td colspan="6" class="ot-empty text-danger">Failed to load.</td></tr>');
                $('#filingPagination').empty();
                $('#filingSummary').text('');
            });
    }

    function applyFilingFilter() {
        const keyword = ($('#filingSearch').val() || '').trim().toLowerCase();

        filteredFilings = allFilings.filter(it => {
            const reason = String(it.reason || '').toLowerCase();
            const status = String(it.status || '').toLowerCase();
            return reason.includes(keyword) || status.includes(keyword);
        });

        renderFilings();
        renderPagination();
    }

    function renderFilings() {
        const $list = $('#my_filing_list');
        $list.empty();

        if (!filteredFilings.length) {
            $list.html('<tr><td colspan="6" class="ot-empty">No filings found.</td></tr>');
            $('#filingSummary').text('0 results');
            return;
        }

        const total = filteredFilings.length;
        const totalPages = Math.ceil(total / pageSize);
        if (currentPage > totalPages) currentPage = totalPages;

        const startIndex = (currentPage - 1) * pageSize;
        const pageItems = filteredFilings.slice(startIndex, startIndex + pageSize);

        pageItems.forEach(it => {
            $list.append(`
                <tr>
                    <td>${formatDate(it.ot_date)}</td>
                    <td>${formatTimeRange(it.time_from, it.time_to)}</td>
                    <td>${escapeHtml(it.reason || '-')}</td>
                    <td>${statusBadge(it.status)}</td>
                    <td>${escapeHtml(it.remarks || '-')}</td>
                    <td>${deleteAction(it)}</td>
                </tr>
            `);
        });

        const from = startIndex + 1;
        const to = Math.min(startIndex + pageItems.length, total);
        $('#filingSummary').text(`Showing ${from}-${to} of ${total} result(s)`);
    }

    function renderPagination() {
        const $pagination = $('#filingPagination');
        $pagination.empty();

        const totalPages = Math.ceil(filteredFilings.length / pageSize);
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

            renderFilings();
            renderPagination();
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

    function deleteAction(filing) {
        const status = String((filing && filing.status) || '').toLowerCase();
        if (!['pending', 'rejected'].includes(status)) return '-';

        return `
            <button type="button"
                    class="btn btn-sm btn-outline-danger btn-delete-filing"
                    data-id="${Number(filing.id)}">
                Delete
            </button>
        `;
    }

    function deleteFiling(id) {
        Swal.fire({
            icon: 'warning',
            title: 'Delete Filing?',
            text: 'This will permanently remove the selected overtime filing.',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#dc3545'
        }).then((result) => {
            if (!result.isConfirmed) return;

            fetch(`${filingDeleteBaseUrl}/${id}`, {
                method: 'DELETE',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
            })
                .then(async (res) => {
                    const data = await res.json();
                    if (!res.ok) throw data;
                    return data;
                })
                .then((data) => {
                    toastr.success(data.message || 'Deleted.', 'Success');
                    getMyFilings();
                })
                .catch((err) => showApiError(err, 'Failed to delete filing.'));
        });
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
