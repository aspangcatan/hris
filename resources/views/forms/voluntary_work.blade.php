<div id="children">
    @if(Route::currentRouteName() == "profile")
        <div class="d-flex justify-content-end align-items-center">
            <button class="btn btn-sm btn-primary" id="btn_add_voluntary">New</button>
        </div>
    @endif
    <div class="table-responsive mt-3">
        <table class="table text-center">
            <thead>
            <tr>
                <th>Name & Organization Address</th>
                <th>Position/Work Nature</th>
                <th>From</th>
                <th>To</th>
                <th style="width: 10%">No. hours</th>
                <th style="width: 16%">Actions</th>
            </tr>
            </thead>
            <tbody id="voluntary_list"></tbody>
        </table>
    </div>
</div>
