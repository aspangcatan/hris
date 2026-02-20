@extends('master')
@section('content')

    <div class="card mt-2">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="fw-bold card-title">Leave Settings</h5>
                <div>
                    <button class="btn btn-sm btn-success" id="btn_add_leave">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table text-center">
                    <thead>
                    <tr>
                        <th>Description</th>
                        <th>Code</th>
                        <th>Monthly points earned</th>
                        <th>Deduction per minute(s)</th>
                        <th>Dedcution per day(s)</th>
                        <th colspan="2" style="width: 4%"></th>
                    </tr>
                    </thead>
                    <tbody id="leave_list"></tbody>
                </table>
                <div id="pagination_container">
                    <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                        <div id="page_items_count"></div>
                        <nav aria-label="Leave settings pagination">
                            <ul class="pagination pagination-sm mb-0" id="pagination_links"></ul>
                        </nav>
                        <div id="page_indicator" class="small text-muted"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('modals.leave_management')
@stop
@section('js')
    @include('js.leave_management')
@stop
