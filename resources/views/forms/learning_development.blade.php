<div>
    @if(Route::currentRouteName() == "profile")
        <div class="d-flex justify-content-end align-items-center">
            <button class="btn btn-sm btn-primary" id="btn_add_learning">New</button>
        </div>
    @endif
    <div class="table-responsive mt-3">
        <table class="table text-center">
            <thead>
            <tr>
                <th>Learning Title</th>
                <th style="width: 5%">From</th>
                <th style="width: 5%">To</th>
                <th style="width: 5%">No. hours</th>
                <th style="width: 10%">Type</th>
                <th style="width: 20%">Conducted/Sponsored by</th>
                <th colspan="2" style="width: 8%"></th>
            </tr>
            </thead>
            <tbody id="learning_list"></tbody>
        </table>
    </div>
</div>
