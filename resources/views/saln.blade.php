@extends('master')
@section('Title', 'SALN Historical Tracking')
@section('content')
    @include('layouts.saln_styles')

    <!-- Header -->
    <div class="saln-header d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h1><i class="bi bi-file-earmark-text me-2"></i>SALN Historical Records</h1>
            <p>Sworn Statement of Assets, Liabilities and Net Worth — Manage your annual filings</p>
        </div>
        <button class="btn btn-light fw-bold" id="btn_open_create_saln">
            <i class="bi bi-plus-circle me-2"></i>CREATE NEW SALN
        </button>
    </div>

    <!-- Filter Bar -->
    <div class="saln-filter-bar d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div>
                <label class="saln-info-label mb-1">Filter by Compliance Year (As Of)</label>
                <select class="form-select form-select-sm" id="filter_compliance_year" style="min-width: 160px;">
                    <option value="">All Years</option>
                    @for($year = \Carbon\Carbon::now()->format('Y'); $year >= 2020; $year--)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endfor
                </select>
            </div>
        </div>
        <div class="text-muted" id="saln_count_label">
            <i class="bi bi-archive me-1"></i>
            <span id="saln_count">0</span> record(s) found
        </div>
    </div>

    <!-- SALN List Section -->
    <div id="saln_records_container">
        <!-- Records will be dynamically populated here -->
        <div class="empty-state" id="saln_empty_state">
            <i class="bi bi-archive d-block"></i>
            <h5>No SALN Records Found</h5>
            <p>Click "Create New SALN" to start your first declaration.</p>
        </div>
    </div>

    <!-- ==================== SALN CREATION/EDITING FULLSCREEN MODAL ==================== -->
    @include('modals.saln_form')

    @include('modals.saln')
@stop
@section('js')
    @include('js.saln')
@stop
