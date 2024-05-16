@extends('master')
@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="fw-bold card-title">Timetable</h5>
                @if(false)
                    <div>
                        <button class="btn btn-sm btn-success" id="btn_file_leave">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                @endif
            </div>
            <div class="d-flex flex-column align-items-center justify-content-center">
                <img src="{{ asset('img/bg_maintenance.png') }}" alt="Maintenance" height="150" width="150">
                <p class="fs-4">Under development</p>
            </div>
        </div>
    </div>
    @include('modals.leave')
@stop
@section('js')
    @include('js.leave_application')
@stop
