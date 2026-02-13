@extends('master')
@section('Title', 'SALN Administration')
@section('content')
    @include('layouts.saln_styles')
    <style>
        .admin-header {
            background: linear-gradient(135deg, #0d6832 0%, #084d26 100%);
            border-radius: 12px;
            padding: 28px 32px;
            color: #fff;
            margin-bottom: 24px;
            box-shadow: 0 4px 16px rgba(13, 104, 50, 0.25);
        }
        .admin-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .admin-info-bar {
            background: #fff;
            border-radius: 10px;
            padding: 16px 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #e9ecef;
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
            border-color: #198754;
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.08);
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
            border-left: 4px solid #198754;
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

    <div class="admin-header">
        <h1><i class="bi bi-shield-lock me-2"></i>SALN Administrative Dashboard</h1>
        <p>Monitor employee compliance, track historical filings, and manage organization-wide SALN records.</p>
    </div>

    <div class="admin-info-bar d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3 flex-grow-1">
            <div class="flex-grow-1" style="max-width: 400px;">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" id="admin_search_employee" placeholder="Search by name or employee ID...">
                </div>
            </div>
            <div>
                <select class="form-select" id="admin_filter_year" style="min-width: 140px;">
                    @for($year = \Carbon\Carbon::now()->format('Y'); $year >= 2020; $year--)
                        <option value="{{ $year }}">{{ $year }} Compliance</option>
                    @endfor
                </select>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-primary" onclick="printSummaryReport()">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Print Summary Report
            </button>
            <span class="badge bg-info text-dark p-2 px-3">
                <i class="bi bi-people-fill me-1"></i> <span id="total_employees">0</span> Staff
            </span>
        </div>
    </div>

    <div class="row" id="employee_grid">
        <!-- Employees will be loaded here -->
    </div>

    <!-- Pagination -->
    <div id="pagination_container" class="d-flex justify-content-center mt-4 mb-5"></div>

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
