@extends('master')

@section('content')
    <style>
        .ho-page {
            --ho-surface: #ffffff;
            --ho-surface-soft: #f7fafc;
            --ho-line: #e4eaf2;
            --ho-text: #10233d;
            --ho-muted: #58708f;
            --ho-brand-1: #0f766e;
            --ho-brand-2: #0ea5a4;
            --ho-success-1: #0f766e;
            --ho-success-2: #0ea5a4;
        }

        .ho-page .ho-shell {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 16px 35px rgba(16, 24, 40, 0.08);
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
        }

        .ho-page .ho-header {
            border-radius: 14px;
            padding: 1rem 1.1rem;
            color: #fff;
            background: linear-gradient(135deg, var(--ho-brand-1), var(--ho-brand-2));
        }

        .ho-page .ho-title {
            margin: 0;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .ho-page .ho-subtitle {
            margin: 0.2rem 0 0;
            opacity: 0.95;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .ho-page .btn-create {
            border: none;
            border-radius: 10px;
            padding: 0.55rem 1rem;
            color: #fff;
            font-weight: 600;
            background: linear-gradient(135deg, var(--ho-success-1), var(--ho-success-2));
            box-shadow: 0 10px 18px rgba(15, 118, 110, 0.24);
        }

        .ho-page .table-wrap {
            margin-top: 1rem;
            border: 1px solid var(--ho-line);
            border-radius: 14px;
            overflow: hidden;
            background: var(--ho-surface);
        }

        .ho-page .ho-table {
            margin-bottom: 0;
        }

        .ho-page .ho-table thead th {
            background: var(--ho-surface-soft);
            color: var(--ho-muted);
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            vertical-align: middle;
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
        }

        .ho-page .ho-table tbody td {
            color: #1d304b;
            font-size: 0.93rem;
            vertical-align: middle;
        }

        .ho-page .ho-table tbody tr:hover {
            background: #f8fbff;
        }

        .ho-page .order-no {
            font-weight: 700;
            color: var(--ho-text);
        }

        .ho-page .date-chip {
            display: inline-block;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            background: #eef4fb;
            color: #3e5878;
            font-weight: 600;
            font-size: 0.82rem;
        }

        .ho-page .btn-assign {
            border-radius: 8px;
            font-weight: 600;
        }

        .ho-modal .modal-content {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 20px 40px rgba(16, 24, 40, 0.14);
        }

        .ho-modal .modal-header {
            border-bottom: 1px solid #edf1f6;
            background: #f8fafd;
        }

        .ho-modal .modal-title {
            font-weight: 700;
            color: #18324f;
        }

        .ho-modal .form-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #4c6584;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.45px;
        }

        .ho-modal .form-control,
        .ho-modal .selectize-input {
            border-radius: 10px;
            border-color: #d6dfeb;
            min-height: 40px;
        }

        .ho-modal .form-control:focus {
            border-color: #0ea5a4;
            box-shadow: 0 0 0 0.2rem rgba(14, 165, 164, 0.14);
        }

        .ho-empty {
            text-align: center;
            color: #6d819a;
            padding: 1.3rem 0.5rem !important;
        }
    </style>

    <div class="ho-page">
        <div class="card ho-shell">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-3 ho-header">
                    <div>
                        <h2 class="ho-title">Hospital Order Management</h2>
                        <p class="ho-subtitle">Create, track, and assign employees to hospital orders</p>
                    </div>
                    <button class="btn btn-success btn-create" data-bs-toggle="modal" data-bs-target="#hospitalOrderModal">
                        <i class="bi bi-plus-circle me-1"></i>Create Hospital Order
                    </button>
                </div>

                <div class="table-responsive table-wrap">
                    <table class="table table-bordered ho-table">
                        <thead>
                        <tr>
                            <th>Hospital Order #</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th style="width: 1%;">Action</th>
                        </tr>
                        </thead>
                        <tbody id="order_list">
                        <tr>
                            <td colspan="4" class="ho-empty">Loading hospital orders...</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="hospitalOrderModal" tabindex="-1" aria-labelledby="hospitalOrderModalLabel"
         aria-hidden="true">
        <div class="modal-dialog ho-modal">
            <form id="hospitalOrderForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Hospital Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Hospital Order #</label>
                            <input type="number" class="form-control" name="order_number" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Hospital Order</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="assignEmployeeModal" tabindex="-1" aria-labelledby="assignEmployeeModalLabel"
         aria-hidden="true">
        <div class="modal-dialog ho-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Employees</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Employee List</label>
                    <select name="employee_ids[]" id="employeeSelect" multiple required></select>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btn_assign">Assign</button>
                </div>
            </div>
        </div>
    </div>

@stop

@section('js')
    <script>
        let ho_id;
        $(document).ready(function () {
            getHospitalOrders();
            loadEmployees();

            $('#hospitalOrderForm').submit(function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                fetch('{{ route("hospital-orders.store") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        toastr.success(data.message, "Success");
                        $('#hospitalOrderModal').modal('hide');
                        this.reset();
                        getHospitalOrders();
                    })
                    .catch(err => {
                        console.error(err);
                        toastr.error("Something went wrong.", "Error");
                    });
            });

            $("#btn_assign").click(function () {
                const $select = $('#employeeSelect')[0].selectize;

                if (!$select) {
                    toastr.error("Employee select not initialized");
                    return;
                }

                const selectedEmployeeIds = $select.getValue();

                if (!selectedEmployeeIds || selectedEmployeeIds.length === 0) {
                    toastr.warning("Please select at least one employee.");
                    return;
                }

                fetch('{{ route("hospital-orders.assign") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        ho_id: ho_id,
                        userid: selectedEmployeeIds
                    })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) {
                            toastr.error(data.error, "Error");
                        } else {
                            toastr.success(data.message, "Success");
                            $('#assignEmployeeModal').modal('hide');
                            getHospitalOrders();
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        toastr.error("Failed to assign employees.", "Error");
                    });
            });
        });

        function getHospitalOrders() {
            fetch("{{ route('hospital-orders.get') }}", {
                method: "GET"
            })
                .then(response => response.json())
                .then(data => {
                    $('#order_list').empty();

                    if (!data || data.length === 0) {
                        $('#order_list').append(`
                            <tr>
                                <td colspan="4" class="ho-empty">No hospital orders found.</td>
                            </tr>
                        `);
                        return;
                    }

                    data.forEach(it => {
                        const start = it.start_date ? new Date(it.start_date).toLocaleDateString() : '-';
                        const end = it.end_date ? new Date(it.end_date).toLocaleDateString() : '-';

                        $('#order_list').append(`
                            <tr>
                                <td class="order-no">#${it.order_number}</td>
                                <td><span class="date-chip">${start}</span></td>
                                <td><span class="date-chip">${end}</span></td>
                                <td>
                                    <button class="btn btn-primary btn-sm btn-assign" onclick="assignEmployee(${it.id})">
                                        <i class="bi bi-people me-1"></i>Assign
                                    </button>
                                </td>
                            </tr>
                        `);
                    });
                })
                .catch(err => {
                    $('#order_list').html(`
                        <tr>
                            <td colspan="4" class="ho-empty">Failed to load hospital orders.</td>
                        </tr>
                    `);
                });
        }

        function assignEmployee(id) {
            ho_id = id;
            const $select = $('#employeeSelect')[0].selectize;
            if ($select) {
                $select.clear();
            }

            fetch("{{ route('hospital-orders.assigned') }}?ho_id=" + ho_id)
                .then(res => res.json())
                .then(data => {
                    if ($select) {
                        $select.setValue(data.assignedUserIds || []);
                    }
                    $('#assignEmployeeModal').modal('show');
                })
                .catch(err => {
                    console.error("Failed to fetch assigned employees", err);
                    toastr.error("Failed to load assigned employees.");
                    $('#assignEmployeeModal').modal('show');
                });
        }

        function loadEmployees() {
            const $select = $('#employeeSelect');

            if ($select[0].selectize) {
                $select[0].selectize.destroy();
            }

            $select.empty();

            fetch("{{ route('employee.selectize') }}")
                .then(res => res.json())
                .then(data => {
                    data.forEach(emp => {
                        const middleInitial = emp.mname ? emp.mname.charAt(0).toUpperCase() + '.' : '';
                        const suffix = emp.suffix ? ' ' + emp.suffix : '';
                        const fullName = `${emp.fname} ${middleInitial} ${emp.lname}${suffix}`.replace(/\s+/g, ' ').trim();

                        $select.append(`<option value="${emp.id}">${fullName}</option>`);
                    });

                    $select.selectize({
                        placeholder: 'Select employees...',
                        maxItems: null,
                        create: false
                    });

                })
                .catch(err => {
                    console.error("Failed to load employees", err);
                    toastr.error("Unable to load employee list.");
                });
        }

    </script>
@stop
