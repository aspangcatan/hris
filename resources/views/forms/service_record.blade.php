<div id="children">
    @if(Route::currentRouteName() == "employee")
        <div class="d-flex justify-content-end align-items-center">
            <button class="btn btn-sm btn-danger me-1" id="btn_print_service_records">Print<i class="ms-1 bi bi-printer"></i></button>
            <button class="btn btn-sm btn-primary ms-1" id="btn_add_service_record">New</button>
        </div>
    @endif
    <div class="table-responsive mt-3">
        <table class="table text-center" style="font-size: 13px">
            <thead>
            <tr>
                <th style="width: 8%">From</th>
                <th style="width: 8%">To</th>
                <th style="width: 20%">Designation</th>
                <th style="width: 10%">Status</th>
                <th style="width: 10%">Salary</th>
                <th style="width: 20%">Office of<br />Assisgnment</th>
                <th style="width: 10%">Leave of<br />Absences W/O pay</th>
                <th>Separation</th>
                <th style="width: 16%">Actions</th>
            </tr>
            </thead>
            <tbody id="service_records_list"></tbody>
        </table>
    </div>
</div>
