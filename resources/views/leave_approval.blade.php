@extends('master')
@section('content')
    <style>
        .leave-board {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        .leave-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .leave-head-title h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .leave-head-title span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .leave-filter {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.25rem;
        }

        .leave-filter .form-control {
            border-radius: 12px;
            border: 1px solid #d8e1ee;
            background: #fff;
        }

        .leave-table-shell {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .leave-table-shell table {
            margin-bottom: 0;
        }

        .leave-table-shell table thead th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .leave-table-shell table tbody td {
            color: #1e293b;
            font-weight: 500;
            vertical-align: middle;
        }
    </style>

    <div class="leave-board">
        <div class="leave-head">
            <div class="leave-head-title">
                <h4>Leave Approvals</h4>
                <span>Review and act on submitted leave applications.</span>
            </div>
        </div>

        <div class="leave-filter">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="text" class="form-control form-control-sm" id="search_employee"
                               placeholder="Search employee">
                        <label for="search_employee">Search Employee</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive leave-table-shell">
            <table class="table text-center table-bordered align-middle">
                <thead>
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

        <div class="mt-3" id="pagination_container">
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
