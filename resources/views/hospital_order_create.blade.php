@extends('master')

@section('content')
    @php
        $canCreateHospitalOrder = in_array(session('access_rights'), ['admin']);
    @endphp

    @if(!$canCreateHospitalOrder)
        <div class="alert alert-danger">You are not authorized to create hospital orders.</div>
    @else
        <style>
            .ho-board {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                padding: 1.5rem;
                box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
            }

            .ho-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                margin-bottom: 1.25rem;
                flex-wrap: wrap;
            }

            .ho-head-title h4 {
                margin: 0;
                font-weight: 700;
                color: #0f172a;
            }

            .ho-head-title span {
                font-size: 0.9rem;
                color: #64748b;
            }

            .ho-panel {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 1rem;
                margin-bottom: 1.25rem;
            }

            .ho-panel .form-control,
            .ho-panel .form-select {
                border-radius: 12px;
                border: 1px solid #d8e1ee;
                background: #fff;
            }

            .ho-label {
                font-size: .76rem;
                text-transform: uppercase;
                letter-spacing: .06em;
                font-weight: 700;
                color: #5e728d;
                margin-bottom: .32rem;
            }

            .ho-table-shell {
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                overflow: hidden;
                background: #fff;
            }

            .ho-table-shell table {
                margin-bottom: 0;
            }

            .ho-table-shell table thead th {
                background: #f8fafc;
                color: #0f172a;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }

            .ho-table-shell table tbody td {
                color: #1e293b;
                font-weight: 500;
                vertical-align: middle;
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

            .ho-search {
                max-width: 320px;
            }
        </style>

        <div class="ho-board">
            <div class="ho-head">
                <div class="ho-head-title">
                    <h4>Hospital Orders</h4>
                    <span>Create date-bound hospital orders and manage the latest records</span>
                </div>
            </div>

            <div class="ho-panel">
                <form id="hospitalOrderForm" class="row g-3 align-items-end">
                    <div class="col-lg-3">
                        <label class="ho-label">Hospital Order #</label>
                        <input type="text" class="form-control" name="order_number" required>
                    </div>
                    <div class="col-lg-2">
                        <label class="ho-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>
                    <div class="col-lg-2">
                        <label class="ho-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>
                    <div class="col-lg-2">
                        <label class="ho-label">Time From</label>
                        <input type="time" class="form-control" name="time_from" required>
                    </div>
                    <div class="col-lg-2">
                        <label class="ho-label">Time To</label>
                        <input type="time" class="form-control" name="time_to" required>
                    </div>
                    <div class="col-lg-1">
                        <button type="submit" class="btn btn-action btn-edit w-100">
                            <i class="bi bi-plus-lg"></i>
                            Create
                        </button>
                    </div>
                </form>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
                <h6 class="mb-0 fw-bold">Hospital Orders</h6>
                <input type="text" id="orderSearch" class="form-control ho-search" placeholder="Search order #...">
            </div>

            <div class="ho-table-shell">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date Range</th>
                            <th>Time Range</th>
                        </tr>
                        </thead>
                        <tbody id="order_list">
                        <tr><td colspan="3" class="ho-empty">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small class="text-muted" id="orderSummary"></small>
                <nav>
                    <ul class="pagination pagination-sm mb-0 ho-pagination" id="orderPagination"></ul>
                </nav>
            </div>
        </div>
    @endif
@stop

@section('js')
<script>
    let allOrders = [];
    let filteredOrders = [];
    let currentPage = 1;
    const pageSize = 8;

    $(document).ready(function () {
        getHospitalOrders();

        $('#orderSearch').on('input', function () {
            currentPage = 1;
            applyOrderFilter();
        });

        $('#hospitalOrderForm').submit(function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('{{ route("hospital-orders.store") }}', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.error || data.errors) throw data;
                toastr.success(data.message || 'Created.', 'Success');
                this.reset();
                getHospitalOrders();
            })
            .catch(err => showApiError(err, 'Failed to create hospital order.'));
        });
    });

    function getHospitalOrders() {
        fetch("{{ route('hospital-orders.get') }}")
            .then(res => res.json())
            .then(data => {
                allOrders = data || [];
                currentPage = 1;
                applyOrderFilter();
            })
            .catch(() => {
                $('#order_list').html('<tr><td colspan="3" class="ho-empty text-danger">Failed to load.</td></tr>');
                $('#orderPagination').empty();
                $('#orderSummary').text('');
            });
    }

    function applyOrderFilter() {
        const keyword = ($('#orderSearch').val() || '').trim().toLowerCase();
        filteredOrders = allOrders.filter(it => String(it.order_number || '').toLowerCase().includes(keyword));
        renderOrders();
        renderOrderPagination();
    }

    function renderOrders() {
        const $list = $('#order_list');
        $list.empty();

        if (!filteredOrders.length) {
            $list.html('<tr><td colspan="3" class="ho-empty">No records found.</td></tr>');
            $('#orderSummary').text('0 results');
            return;
        }

        const total = filteredOrders.length;
        const totalPages = Math.ceil(total / pageSize);
        if (currentPage > totalPages) currentPage = totalPages;

        const startIndex = (currentPage - 1) * pageSize;
        const pageItems = filteredOrders.slice(startIndex, startIndex + pageSize);

        pageItems.forEach(it => {
            const dateRange = `${formatDate(it.start_date)} - ${formatDate(it.end_date)}`;
            const timeRange = formatTimeRange(it.time_from, it.time_to);
            $list.append(`
                <tr>
                    <td><span class="ho-chip">#${escapeHtml(it.order_number || '')}</span></td>
                    <td>${dateRange}</td>
                    <td>${timeRange}</td>
                </tr>
            `);
        });

        const from = startIndex + 1;
        const to = Math.min(startIndex + pageItems.length, total);
        $('#orderSummary').text(`Showing ${from}-${to} of ${total} result(s)`);
    }

    function renderOrderPagination() {
        const $pagination = $('#orderPagination');
        $pagination.empty();

        const totalPages = Math.ceil(filteredOrders.length / pageSize);
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

            renderOrders();
            renderOrderPagination();
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
