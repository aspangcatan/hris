@extends('master')

@section('content')
<div class="pagetitle">
    <h1>Permission Levels</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('profile') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.permissions') }}">Access Rights</a></li>
            <li class="breadcrumb-item active">Permission Levels</li>
        </ol>
    </nav>
</div>

<style>
    .perm-level-row + .perm-level-row { border-top: 1px solid #e9ecef; }
    #permission_levels_table th, #permission_levels_table td { vertical-align: middle; }
</style>

<section class="section">
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Default Permissions by Level</h5>
            <p class="text-muted">
                Toggle which permissions are granted by default to each access level.
                Changes here do not affect individual users' custom permission overrides.
            </p>
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="permission_levels_table">
                    <thead id="matrix_thead"></thead>
                    <tbody id="matrix_tbody">
                        <tr><td class="text-muted text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script>
const ROUTE_DATA   = "{{ route('admin.permission_levels.data') }}";
const ROUTE_UPDATE = "{{ route('admin.permission_levels.update') }}";
const CSRF         = "{{ csrf_token() }}";

const MODULE_INFO = {
    dtr:            { label: 'DTR',            icon: 'bi-calendar-check' },
    employee:       { label: 'Employee',       icon: 'bi-person-badge' },
    leave:          { label: 'Leave',          icon: 'bi-calendar-event' },
    payroll:        { label: 'Payroll',        icon: 'bi-cash-stack' },
    overtime:       { label: 'Overtime',       icon: 'bi-clock-history' },
    passslip:       { label: 'Pass Slip',      icon: 'bi-door-open' },
    schedule:       { label: 'Schedule',       icon: 'bi-calendar3' },
    saln:           { label: 'SALN',           icon: 'bi-file-earmark-text' },
    hospital_order: { label: 'Hospital Order', icon: 'bi-hospital' },
    reports:        { label: 'Reports',        icon: 'bi-bar-chart-line' },
};

const LEVEL_LABELS = {
    admin: 'Admin', dh: 'DH', hr: 'HR', schedule: 'Schedule', sh: 'SH', standard: 'Standard',
};

const PERM_DESCRIPTIONS = {};

async function loadMatrix() {
    const res  = await fetch(ROUTE_DATA, { headers: { Accept: 'application/json' } });
    const data = await res.json();
    renderMatrix(data);
}

function renderMatrix(data) {
    const levels = data.levels;

    const headerCells = levels.map(l => `<th class="text-center">${LEVEL_LABELS[l] || l.toUpperCase()}</th>`).join('');
    document.getElementById('matrix_thead').innerHTML = `<tr><th>Permission</th>${headerCells}</tr>`;

    const bodyRows = Object.entries(data.modules).map(([module, perms]) => {
        const info = MODULE_INFO[module] || { label: module, icon: 'bi-gear' };
        const moduleHeader = `
            <tr class="table-light">
                <th colspan="${levels.length + 1}"><i class="bi ${info.icon} me-1"></i> ${info.label}</th>
            </tr>`;
        perms.forEach(p => { PERM_DESCRIPTIONS[p.code] = p.description; });
        const permRows = perms.map(p => renderPermRow(p, levels)).join('');
        return moduleHeader + permRows;
    }).join('');

    document.getElementById('matrix_tbody').innerHTML = bodyRows;
}

function renderPermRow(p, levels) {
    const cells = levels.map(level => {
        if (level === 'admin') {
            return `<td class="text-center"><input type="checkbox" class="form-check-input" checked disabled></td>`;
        }
        const checked = p.levels[level] ? 'checked' : '';
        return `<td class="text-center">
            <input type="checkbox" class="form-check-input" style="cursor:pointer"
                   ${checked}
                   onchange="toggleLevelPermission(this, '${level}', '${p.code}')">
        </td>`;
    }).join('');

    return `<tr class="perm-level-row"><td>${p.description}</td>${cells}</tr>`;
}

async function toggleLevelPermission(checkbox, level, code) {
    const granted = checkbox.checked;

    const res = await fetch(ROUTE_UPDATE, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF,
        },
        body: JSON.stringify({ level, code, granted }),
    });

    if (!res.ok) {
        checkbox.checked = !granted;
        toastr.error('Failed to update permission. Please try again.', 'Error');
        return;
    }

    const action = granted ? 'granted to' : 'revoked from';
    toastr.success(`${PERM_DESCRIPTIONS[code] || code} ${action} ${LEVEL_LABELS[level] || level}`, 'Updated');
}

loadMatrix();
</script>
@endsection
