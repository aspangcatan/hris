@extends('master')
@section('content')
@php
    $canCreateHospitalOrder = in_array(session('access_rights'), ['admin']);
    $canApproveHospitalOrder = in_array(session('access_rights'), ['admin'], true) || session('is_section_head') || session('is_division_head');
@endphp
<style>
.ho-nav-board{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:1.5rem;box-shadow:0 18px 38px rgba(15,23,42,.08)}
.ho-nav-head{margin-bottom:1.5rem}
.ho-nav-head h4{margin:0;font-weight:700;color:#0f172a}
.ho-nav-head span{font-size:.9rem;color:#64748b}
.ho-nav-card{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.6rem;padding:1.75rem 1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;text-decoration:none;color:#0f172a;font-weight:600;font-size:.95rem;transition:all .2s ease;text-align:center}
.ho-nav-card:hover{border-color:#0f766e;background:#fff;color:#0f766e;box-shadow:0 8px 20px rgba(15,118,110,.12);transform:translateY(-2px)}
.ho-nav-card i{font-size:1.8rem;color:#0f766e}
</style>

<div class="ho-nav-board">
    <div class="ho-nav-head">
        <h4>Hospital Order Module</h4>
        <span>Select the action you want to perform.</span>
    </div>

    <div class="row g-3">
        @if($canCreateHospitalOrder)
        <div class="col-md-4">
            <a href="{{ route('hospital_order_create') }}" class="ho-nav-card">
                <i class="bi bi-file-earmark-plus-fill"></i>
                Create Hospital Order
            </a>
        </div>
        @endif
        <div class="col-md-4">
            <a href="{{ route('hospital_order_file') }}" class="ho-nav-card">
                <i class="bi bi-folder2-open"></i>
                File Hospital Order
            </a>
        </div>
        @if($canApproveHospitalOrder)
        <div class="col-md-4">
            <a href="{{ route('hospital_order_approval') }}" class="ho-nav-card">
                <i class="bi bi-check2-circle"></i>
                Approve / Reject Filings
            </a>
        </div>
        @endif
    </div>
</div>
@stop
