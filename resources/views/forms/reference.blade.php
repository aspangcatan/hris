<div>
    @if(Route::currentRouteName() == "profile")
        <div class="d-flex justify-content-end align-items-center">
            <button class="btn btn-sm btn-primary" id="btn_add_reference">New</button>
        </div>
    @endif
    <div class="table-responsive mt-3">
        <table class="table text-center" style="font-size: 13px">
            <thead>
            <tr>
                <th style="width: 40%">Name</th>
                <th>Address</th>
                <th>Contact No.</th>
                <th style="width: 16%">Actions</th>
            </tr>
            </thead>
            <tbody id="reference_list"></tbody>
        </table>
    </div>
</div>
