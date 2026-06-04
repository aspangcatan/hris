<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Leave Credits — Batch #{{ $batch->id }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; font-family: Arial, sans-serif; font-size: 10px; color: #1a1a1a; }

        .header { text-align: center; margin-bottom: 8px; }
        .header h2 { font-size: 14px; margin: 0 0 2px; }
        .header p  { font-size: 9px; margin: 0; color: #555; }

        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #aaa; padding: 4px 6px; vertical-align: middle; }
        thead th { background: #1a6e3c; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; text-align: center; }
        tbody tr:nth-child(even) { background: #f4f9f6; }
        td { font-size: 9.5px; }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }

        .footer { margin-top: 12px; font-size: 8.5px; color: #666; display: flex; justify-content: space-between; }

        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="padding:8px; background:#f0f0f0; margin-bottom:8px;">
        <button onclick="window.print()" style="padding:6px 16px; background:#1a6e3c; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:12px;">
            🖨 Print
        </button>
        <button onclick="window.close()" style="padding:6px 16px; margin-left:8px; background:#666; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:12px;">
            ✕ Close
        </button>
    </div>

    <div class="header">
        <h2>Bulk Leave Credit Assignment</h2>
        <p>
            Batch #{{ $batch->id }} &nbsp;|&nbsp;
            Date: {{ \Carbon\Carbon::parse($batch->created_at)->format('F d, Y h:i A') }} &nbsp;|&nbsp;
            Processed by: {{ $batch->admin_lname }}, {{ $batch->admin_fname }}
            @if($batch->notes)
                &nbsp;|&nbsp; Notes: {{ $batch->notes }}
            @endif
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Designation</th>
                <th>Division</th>
                <th>Section</th>
                <th>Employee Type</th>
                <th>Credits Added</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($grouped as $i => $row)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $row->lastname }}, {{ $row->firstname }}</td>
                <td>{{ $row->designation_name ?? '—' }}</td>
                <td>{{ $row->division_name ?? '—' }}</td>
                <td>{{ $row->section_name ?? '—' }}</td>
                <td class="text-center">{{ $row->employee_type ?? '—' }}</td>
                <td>{{ $row->credits_added }}</td>
                <td>{{ $row->balance_after }}</td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center">No records.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <span>Total employees: {{ $grouped->count() }}</span>
        <span>Generated: {{ now()->format('m/d/Y h:i A') }}</span>
    </div>

</body>
</html>
