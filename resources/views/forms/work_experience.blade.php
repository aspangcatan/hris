<div id="children">
    @if(Route::currentRouteName() == "profile")
        <div class="d-flex justify-content-end align-items-center">
            <button class="btn btn-sm btn-primary" id="btn_add_work">New</button>
        </div>
    @endif
    <div class="table-responsive mt-3">
        <table class="table text-center">
            <thead>
                <tr>
                    <th>Job Title</th>
                    <th style="width: 30%">Company</th>
                    <th style="width: 5%">From</th>
                    <th style="width: 5%">To</th>
                    <th style="width: 12%">Salary</th>
                    <th>Grade</th>
                    <th style="width: 10%">Status Appointment</th>
                    <th style="width: 10%">Gov't Service (Y/N)</th>
                    <th colspan="2" style="width: 8%"></th>
                </tr>
            </thead>
            <tbody id="work_list"></tbody>
        </table>
    </div>
</div>
