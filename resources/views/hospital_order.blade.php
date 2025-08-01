@extends('master')

@section('content')
    <div class="card">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Hospital Order Management</h2>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#hospitalOrderModal">
                    + Create Hospital Order
                </button>
            </div>

            <!-- Hospital Orders Table -->
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                    <tr>
                        <th>Hospital Order #</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th style="width:2%"></th>
                    </tr>
                    </thead>
                    <tbody id="order_list"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Hospital Order Modal -->
    <div class="modal fade" id="hospitalOrderModal" tabindex="-1" aria-labelledby="hospitalOrderModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <form id="hospitalOrderForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Hospital Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-3">
                        <div class="col-md-12">
                            <label>Hospital Order #</label>
                            <input type="number" class="form-control" name="order_number" required>
                        </div>
                        <div class="col-md-12">
                            <label>Start Date</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-12">
                            <label>End Date</label>
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

    <!-- Assign Employee Modal -->
    <div class="modal fade" id="assignEmployeeModal" tabindex="-1" aria-labelledby="assignEmployeeModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Employees</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
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

                const selectedEmployeeIds = $select.getValue(); // gets array of selected employee IDs

                if (!selectedEmployeeIds || selectedEmployeeIds.length === 0) {
                    toastr.warning("Please select at least one employee.");
                    return;
                }

                fetch('{{ route("hospital-orders.assign") }}', { // update with your route
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        ho_id: ho_id,
                        userid: selectedEmployeeIds // send array of IDs
                    })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) {
                            toastr.error(data.error, "Error");
                        } else {
                            toastr.success(data.message, "Success");
                            $('#assignEmployeeModal').modal('hide');
                            getHospitalOrders(); // refresh list or update UI
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
                    data.forEach(it => {
                        $('#order_list').append(`
                <tr>
                    <td>${it.order_number}</td>
                    <td>${it.start_date}</td>
                    <td>${it.end_date}</td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="assignEmployee(${it.id})">
                            <i class="bi bi-plus"></i>
                        </button>
                    </td>
                </tr>`);
                    });
                })
                .catch(err => {
                    alert("Ooops, something went wrong. Please reload");
                });
        }

        function assignEmployee(id) {
            ho_id = id;
            const $select = $('#employeeSelect')[0].selectize;
            if ($select) {
                $select.clear(); // Clear previous selections
            }

            // Fetch assigned employees for this ho_id
            fetch("{{ route('hospital-orders.assigned') }}?ho_id=" + ho_id)
                .then(res => res.json())
                .then(data => {
                    if ($select) {
                        // data should be an array of assigned user IDs
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

            // Clear and destroy existing Selectize if needed
            if ($select[0].selectize) {
                $select[0].selectize.destroy();
            }

            $select.empty(); // Clear existing options

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
