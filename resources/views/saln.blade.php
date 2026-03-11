@extends('master')
@section('Title', 'SALN Historical Tracking')
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

        .saln-filter-lite .form-select {
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
    </style>

    <div class="saln-board">
        <div class="saln-head">
            <div class="saln-head-title">
                <h4>SALN Historical Records</h4>
                <span>Sworn Statement of Assets, Liabilities and Net Worth</span>
            </div>
            <button class="btn btn-edit" id="btn_open_create_saln">
                <i class="bi bi-plus-circle"></i>
                CREATE NEW SALN
            </button>
        </div>

        <div class="saln-filter-lite d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <label class="saln-info-label mb-1">Filter by Compliance Year (As Of)</label>
                <select class="form-select form-select-sm" id="filter_compliance_year" style="min-width: 160px;">
                    <option value="">All Years</option>
                    @for($year = \Carbon\Carbon::now()->format('Y'); $year >= 2020; $year--)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endfor
                </select>
            </div>
            <div class="saln-count-badge" id="saln_count_label">
                <i class="bi bi-archive"></i>
                <span id="saln_count">0</span> record(s) found
            </div>
        </div>

        <div id="saln_records_container">
            <div class="empty-state" id="saln_empty_state">
                <i class="bi bi-archive d-block"></i>
                <h5>No SALN Records Found</h5>
                <p>Click "Create New SALN" to start your first declaration.</p>
            </div>
        </div>
    </div>

    <!-- ==================== SALN CREATION/EDITING FULLSCREEN MODAL ==================== -->
    @include('modals.saln_form')

    @include('modals.saln')
@stop
@section('js')
    @include('js.saln')
@stop
