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
                <div class="col-md-3 offset-md-6">
                    <button id="filterBtn" class="btn btn-success w-100 btn-lg">
                        <i class="bi bi-funnel"></i> Apply Filters
                    </button>
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
            <div class="d-flex align-items-center justify-content-center">
                <button class="btn btn-sm btn-outline-secondary d-none" id="btn_prev">
                    <i class="bi bi-caret-left"></i>
                </button>
                <div id="page_items_count" class="mx-3 small text-muted"></div>
                <button class="btn btn-sm btn-outline-secondary" id="btn_next">
                    <i class="bi bi-caret-right"></i>
                </button>
            </div>
        </div>
    </div>

    @include('modals.leave')
@stop

@section('js')
    @include('js.leave_approval')
@stop
