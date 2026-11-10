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
            font-size: 0.85rem;
        }

        .lwop-rule-list {
            margin: 0;
            padding-left: 1rem;
            text-align: left;
        }

        .lwop-rule-list li {
            margin-bottom: 0.15rem;
        }
    </style>

    <div class="leave-board">
        <div class="leave-head">
            <div>
                <h4>LWOP Settings</h4>
                <span>
                    Rules for counting leave without pay and earned credits (CSC MC No. 41, s. 1998, Rule XVI, Sec. 33).
                    Each cutoff uses the rule set in force on its start date. When a rule changes, add a new rule set
                    with its effective date. Rule sets already used by a cutoff are locked.
                </span>
            </div>
            <button class="btn btn-success btn-edit" id="btn_add_lwop_rule">
                <i class="bi bi-plus-circle me-1"></i>
                Add Rule Set
            </button>
        </div>

        <div class="table-responsive leave-table-shell">
            <table class="table align-middle text-center">
                <thead>
                <tr>
                    <th>Effective From</th>
                    <th>LWOP Identification</th>
                    <th>Earned Credits</th>
                    <th>Legal Basis / Remarks</th>
                    <th>Used By</th>
                    <th>Last Change</th>
                    <th colspan="2" style="width: 4%"></th>
                </tr>
                </thead>
                <tbody id="lwop_rule_list"></tbody>
            </table>
        </div>
    </div>

    @include('modals.lwop_rules')
@stop
@section('js')
    @include('js.lwop_rules')
@stop
