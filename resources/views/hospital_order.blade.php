@extends('master')

@section('content')
    @php
        $canCreateHospitalOrder = in_array(session('access_rights'), ['admin']);
        $canApproveHospitalOrder = in_array(session('access_rights'), ['admin'], true) || session('is_section_head') || session('is_division_head');
    @endphp

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h4 class="mb-3">Hospital Order Module</h4>
            <p class="text-muted mb-4">Choose the page you need.</p>

            <div class="row g-3">
                @if($canCreateHospitalOrder)
                    <div class="col-md-4">
                        <a href="{{ route('hospital_order_create') }}" class="btn btn-outline-primary w-100 py-3">Create Hospital Order</a>
                    </div>
                @endif
                <div class="col-md-4">
                    <a href="{{ route('hospital_order_file') }}" class="btn btn-outline-success w-100 py-3">File Hospital Order</a>
                </div>
                @if($canApproveHospitalOrder)
                    <div class="col-md-4">
                        <a href="{{ route('hospital_order_approval') }}" class="btn btn-outline-warning w-100 py-3">Approve / Reject Filings</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@stop
