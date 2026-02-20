@extends('master')
@section('content')
    <div class="card shadow-sm border-0 rounded-3" style="background-color: #f8f9fa;">
        <div class="card-header bg-white border-bottom py-3">
            <h4 class="mb-0 fw-bold text-dark">📝 Leave Applications for Approval</h4>
        </div>

        <div class="card-body bg-white">

            <!-- Filter Section -->
            <div class="row g-3 align-items-end mb-4 mt-2">
                <div class="col-md-3">
                    <div class="form-floating">
                        <input type="text" class="form-control form-control-sm" id="search_employee"
                               placeholder="Search employee">
                        <label for="search_employee">Search Employee</label>
                    </div>
                </div>
            </div>

            <!-- Divider -->
            <hr class="my-3">

            <!-- Applications Table -->
            <div class="table-responsive">
                <table class="table text-center table-bordered align-middle" style="background-color: #fff;">
                    <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Date Created</th>
                        <th style="width: 4%"></th>
                    </tr>
                    </thead>
                    <tbody id="leave_approval_list">
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            No applications found.
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Pagination -->
        <div class="card-footer bg-white border-top text-dark" id="pagination_container">
            <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                <div id="page_items_count" class="small text-muted"></div>
                <nav aria-label="Leave approval pagination">
                    <ul class="pagination pagination-sm mb-0" id="pagination_links"></ul>
                </nav>
                <div id="page_indicator" class="small text-muted"></div>
            </div>
        </div>
    </div>

    @include('modals.leave')
@stop

@section('js')
    @include('js.leave_approval')
@stop
