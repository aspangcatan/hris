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

        .leave-head h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .leave-head span {
            font-size: 0.9rem;
            color: #64748b;
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
            <div>
                <h4>Leave Settings</h4>
                <span>Maintain leave types, monthly earning rules, and availability.</span>
            </div>
            <button class="btn btn-success btn-edit" id="btn_add_leave">
                <i class="bi bi-plus-circle me-1"></i>
                Add Leave Type
            </button>
        </div>

        <div class="table-responsive leave-table-shell">
            <table class="table align-middle text-center">
                <thead>
                <tr>
                    <th>Description</th>
                    <th>Code</th>
                    <th>Status</th>
                    <th>Monthly Earned</th>
                    <th colspan="2" style="width: 4%"></th>
                </tr>
                </thead>
                <tbody id="leave_list"></tbody>
            </table>
        </div>

        <div class="mt-3" id="pagination_container">
            <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                <div id="page_items_count"></div>
                <nav aria-label="Leave settings pagination">
                    <ul class="pagination pagination-sm mb-0" id="pagination_links"></ul>
                </nav>
                <div id="page_indicator" class="small text-muted"></div>
            </div>
        </div>
    </div>

    @include('modals.leave_management')
@stop
@section('js')
    @include('js.leave_management')
@stop
