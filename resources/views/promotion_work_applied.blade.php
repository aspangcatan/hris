@extends('master')
@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title">Positions Applied</h5>
                <div>
                    <button class="btn btn-sm btn-success" id="btn_add_position">ADD NEW</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table text-center">
                    <thead>
                    <tr>
                        <th>Position Applied</th>
                        <th>Date Applied</th>
                        <th style="width: 4%"></th>
                    </tr>
                    </thead>
                    <tbody id="work_list"></tbody>
                </table>
            </div>
        </div>
    </div>
    @include('modals.promotion_work_applied')
@stop
@section('js')
    @include('js.promotion_work_applied')
@stop
