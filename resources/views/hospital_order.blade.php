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
                        <th>Assigned Employees</th>
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
@stop

@section('js')
    <script>
        $(document).ready(function (){
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
                        console.log(data);
                        toastr.success(data.message, "Success");
                        $('#hospitalOrderModal').modal('hide');
                        this.reset();
                    })
                    .catch(err => {
                        console.error(err);
                        toastr.error("Something went wrong.", "Error");
                    });
            });
        });

        public function asd(){

        }
    </script>
@stop
