<div id="civil_service">
    @if(Route::currentRouteName() == "profile")
        <div class="d-flex justify-content-end align-items-center">
            <button class="btn btn-sm btn-primary" id="btn_add_civil_service">New</button>
        </div>
    @endif
    <div class="table-responsive mt-3">
        <table class="table text-center">
            <thead>
            <tr>
                <th>Career Service</th>
                <th style="width: 5%">Rating</th>
                <th style="width: 10%">Date of<br/>examination</th>
                <th style="width: 30%">Place of <br/>examination</th>
                <th style="width: 10%">License No.</th>
                <th style="width: 10%">Date of<br/>validity</th>
                <th colspan="3" style="width: 8%"></th>
            </tr>
            </thead>
            <tbody id="civil_service_list">
            </tbody>
        </table>
    </div>
</div>
