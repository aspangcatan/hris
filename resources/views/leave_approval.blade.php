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

        {{-- Tabs --}}
        <ul class="nav nav-tabs mb-3" id="approvalTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-leave-approval" data-bs-toggle="tab"
                        data-bs-target="#pane-leave-approval" type="button" role="tab">
                    Leave Applications
                </button>
            </li>
            {{-- Monetization hidden for now
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-monetization-approval" data-bs-toggle="tab"
                        data-bs-target="#pane-monetization-approval" type="button" role="tab">
                    Monetization Requests
                </button>
            </li>
            --}}
        </ul>

        <div class="tab-content" id="approvalTabContent">

            {{-- Tab 1: Leave Applications --}}
            <div class="tab-pane fade show active" id="pane-leave-approval" role="tabpanel">
                <div class="leave-filter">
                    <div class="row g-3 align-items-end">
                        <div class="col-12">
                            <div class="btn-group" role="group" aria-label="Approval status filter">
                                <button type="button" class="btn btn-sm btn-outline-primary active" data-filter="pending">
                                    Pending for Approval
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-filter="approved_by_stage">
                                    Approved by This Stage
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-filter="approved_all">
                                    Approved in All Stages
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control form-control-sm" id="search_employee"
                                       placeholder="">
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
                            <th>Dates Applied</th>
                            <th>Date Created</th>
                            <th style="width: 4%"></th>
                        </tr>
                        </thead>
                        <tbody id="leave_approval_list">
                        <tr>
                            <td colspan="5" class="text-center text-muted">No applications found.</td>
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

            {{-- Tab 2: Monetization Requests --}}
            <div class="tab-pane fade" id="pane-monetization-approval" role="tabpanel">
                <div class="leave-filter">
                    <div class="row g-3 align-items-end">
                        <div class="col-12">
                            <div class="btn-group" role="group" aria-label="Monetization filter">
                                <button type="button" class="btn btn-sm btn-outline-primary active" data-monetization-filter="pending">
                                    Pending for Approval
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-monetization-filter="approved_by_stage">
                                    Approved by This Stage
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-monetization-filter="approved_all">
                                    Approved in All Stages
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control form-control-sm" id="search_employee_monetization"
                                       placeholder="">
                                <label for="search_employee_monetization">Search Employee</label>
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
                            <th>Days Requested</th>
                            <th>Filed Date</th>
                            <th style="width: 4%"></th>
                        </tr>
                        </thead>
                        <tbody id="monetization_approval_list">
                        <tr>
                            <td colspan="5" class="text-center text-muted">No requests found.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3" id="monetization_approval_pagination_container">
                    <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                        <div id="monetization_approval_page_items_count" class="small text-muted"></div>
                        <nav aria-label="Monetization approval pagination">
                            <ul class="pagination pagination-sm mb-0" id="monetization_approval_pagination_links"></ul>
                        </nav>
                        <div id="monetization_approval_page_indicator" class="small text-muted"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @include('modals.leave')
    @include('modals.leave_flag_warning')
    @include('modals.monetize_approve')
@stop

@section('js')
    @include('js.leave_approval')
@stop
