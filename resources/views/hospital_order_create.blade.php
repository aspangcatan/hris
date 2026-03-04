@extends('master')

@section('content')
    @php
        $canCreateHospitalOrder = in_array(session('access_rights'), ['admin', 'calendar']);
    @endphp

    @if(!$canCreateHospitalOrder)
        <div class="alert alert-danger">You are not authorized to create hospital orders.</div>
    @else
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Create Hospital Order</h4>
                    <a href="{{ route('hospital_order') }}" class="btn btn-sm btn-secondary">Back</a>
                </div>

                <form id="hospitalOrderForm" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Hospital Order #</label>
                        <input type="text" class="form-control" name="order_number" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Time From</label>
                        <input type="time" class="form-control" name="time_from" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Time To</label>
                        <input type="time" class="form-control" name="time_to" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>

                <h6>Hospital Orders</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Date Range</th>
                            <th>Time Range</th>
                        </tr>
                        </thead>
                        <tbody id="order_list">
                        <tr><td colspan="3" class="text-center text-muted">Loading...</td></tr>
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
        getHospitalOrders();

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
                $('#order_list').empty();
                if (!data || data.length === 0) {
                    $('#order_list').html('<tr><td colspan="3" class="text-center text-muted">No records found.</td></tr>');
                    return;
                }

                data.forEach(it => {
                    const dateRange = `${formatDate(it.start_date)} - ${formatDate(it.end_date)}`;
                    const timeRange = formatTimeRange(it.time_from, it.time_to);
                    $('#order_list').append(`<tr><td>#${escapeHtml(it.order_number || '')}</td><td>${dateRange}</td><td>${timeRange}</td></tr>`);
                });
            })
            .catch(() => $('#order_list').html('<tr><td colspan="3" class="text-center text-danger">Failed to load.</td></tr>'));
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
