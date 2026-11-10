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

        .lwop-week {
            border-collapse: collapse;
            width: 100%;
        }

        .lwop-week th,
        .lwop-week td {
            border: 2px solid #1e293b;
            padding: 0.55rem 0.75rem;
            text-align: center;
            vertical-align: middle;
        }

        .lwop-week thead th {
            font-weight: 600;
            letter-spacing: 0.04em;
        }

        .lwop-week .day-off {
            background: #f1f5f9;
        }

        .lwop-week .is-lwop {
            font-weight: 700;
            color: #b91c1c;
        }

        .lwop-week .is-present {
            color: #15803d;
        }
    </style>

    <div class="leave-board">
        <div class="leave-head">
            <div>
                <h4>LWOP Settings</h4>
                <span>
                    Saturday and Sunday are OFF. Each case says, from Friday and Monday, whether that Saturday and Sunday
                    count as LWOP. A weekend with no matching case counts as present. Holidays always count as present.
                    Cases change LWOP days and earned credits (days present = 30 &minus; days without pay), never pay.
                </span>
            </div>
            <button class="btn btn-success btn-edit" id="btn_add_lwop_case">
                <i class="bi bi-plus-circle me-1"></i>
                Add Case
            </button>
        </div>

        <div class="table-responsive">
            <table class="lwop-week">
                <thead>
                <tr>
                    <th style="width: 3rem">#</th>
                    <th>FRI</th>
                    <th class="day-off">SAT</th>
                    <th class="day-off">SUN</th>
                    <th>MON</th>
                    <th>Result</th>
                    <th>Remarks</th>
                    <th style="width: 6rem"></th>
                </tr>
                </thead>
                <tbody id="lwop_case_list"></tbody>
            </table>
        </div>
    </div>

    @include('modals.lwop_cases')
@stop
@section('js')
    @include('js.lwop_cases')
@stop
