@extends('master')
@section('Title', 'SALN Administration')
@section('content')
    @include('layouts.saln_styles')
    <style>
        .saln-board {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        .saln-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .saln-head-title h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .saln-head-title span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .saln-filter-lite {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.25rem;
        }

        .saln-filter-lite .form-select,
        .saln-filter-lite .form-control {
            border-radius: 12px;
            border: 1px solid #d8e1ee;
            background: #fff;
        }

        .saln-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            padding: 0.35rem 0.75rem;
            color: #475569;
            font-size: 0.85rem;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-submitted { background: #d1ecf1; color: #0c5460; }
        .status-pending { background: #f8d7da; color: #721c24; }

        .employee-card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            padding: 15px 20px;
            margin-bottom: 10px;
            transition: all 0.2s ease;
        }
        .employee-card:hover {
            border-color: #0f766e;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.1);
        }
        .employee-name {
            font-weight: 700;
            color: #212529;
            margin-bottom: 2px;
        }
        .employee-no {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .history-year-card {
            border-left: 4px solid #0f766e;
            background: #f8f9fa;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 0 8px 8px 0;
            cursor: pointer;
            transition: background 0.2s;
        }
        .history-year-card:hover {
            background: #e9ecef;
        }
    </style>

    <div class="saln-board">
        <div class="saln-head">
            <div class="saln-head-title">
                <h4>SALN Administrative Dashboard</h4>
                <span>Monitor employee compliance, track historical filings, and manage organization-wide SALN records.</span>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button class="btn btn-action btn-view" onclick="printSummaryReport()">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Print Summary Report
                </button>
                <span class="saln-count-badge">
                    <i class="bi bi-people-fill"></i> <span id="total_employees">0</span> Staff
                </span>
            </div>
        </div>

        <div class="saln-filter-lite d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="flex-grow-1" style="max-width: 420px;">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" id="admin_search_employee" placeholder="Search by name or employee ID...">
                </div>
            </div>
            <div>
                <select class="form-select" id="admin_filter_year" style="min-width: 160px;">
                    @for($year = \Carbon\Carbon::now()->format('Y'); $year >= 2020; $year--)
                        <option value="{{ $year }}">{{ $year }} Compliance</option>
                    @endfor
                </select>
            </div>
        </div>

        <div class="row" id="employee_grid">
            <!-- Employees will be loaded here -->
        </div>

        <div id="pagination_container" class="d-flex justify-content-center mt-4 mb-5"></div>
    </div>

    <!-- Employee History Modal -->
    <div class="modal fade" id="employee_history_modal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Filing History: <span id="target_emp_name"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="history_list_container">
                        <!-- History items -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('modals.saln_form')
    @include('modals.saln')
@endsection

@section('js')
    @include('js.saln_admin')
    @include('js.saln')
@endsection
