@extends('master')

@section('content')
<div class="pagetitle">
    <h1>Access Rights Management</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            <li class="breadcrumb-item active">Access Rights</li>
        </ol>
    </nav>
</div>

<style>
    .perm-row + .perm-row { border-top: 1px solid #e9ecef; }
</style>

<section class="section">
    <div class="row">
        {{-- LEFT: User List --}}
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Employees</h5>
                    <div class="mb-3">
                        <input type="text" id="user_search" class="form-control"
                               placeholder="Search by name..." autocomplete="off">
                    </div>
                    <div id="user_list" style="max-height:65vh; overflow-y:auto;">
                        <p class="text-muted text-center">Loading...</p>
                    </div>
                    <div id="user_pagination" class="mt-2 d-flex justify-content-between align-items-center"></div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Permission Panel --}}
        <div class="col-md-8">
            <div class="card h-100" id="permission_panel" style="display:none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="card-title mb-0" id="panel_user_name">—</h5>
                            <small class="text-muted">Level: <span id="panel_user_level" class="badge bg-secondary"></span></small>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" id="btn_reset_all">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset to Level Defaults
                        </button>
                    </div>

                    <div id="permissions_by_module"></div>
                </div>
            </div>
            <div class="card h-100" id="permission_placeholder">
                <div class="card-body d-flex align-items-center justify-content-center">
                    <p class="text-muted">Select an employee to manage their access rights.</p>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@section('js')
<script>
const ROUTE_USERS      = "{{ route('admin.permissions.users') }}";
const ROUTE_USER_PERMS = "{{ url('/admin/permissions/users') }}";
const CSRF             = "{{ csrf_token() }}";

let currentUserId  = null;
let userPage       = 1;
let searchTimeout  = null;

async function loadUsers(page = 1) {
    const search = document.getElementById('user_search').value.trim();
    const url    = `${ROUTE_USERS}?page=${page}&search=${encodeURIComponent(search)}`;
    const res    = await fetch(url, { headers: { Accept: 'application/json' } });
    const body   = await res.json();

    const list = document.getElementById('user_list');
    list.innerHTML = body.data.length === 0
        ? '<p class="text-muted text-center">No employees found.</p>'
        : body.data.map(u => `
            <div class="d-flex align-items-center justify-content-between border-bottom py-2 user-row"
                 style="cursor:pointer" data-id="${u.id}" onclick="selectUser(${u.id})">
                <span>${u.lname}, ${u.fname}</span>
                <span class="badge bg-secondary">${u.level}</span>
            </div>`).join('');

    const pag = document.getElementById('user_pagination');
    pag.innerHTML = `
        <button class="btn btn-sm btn-outline-secondary" ${body.current_page <= 1 ? 'disabled' : ''}
                onclick="loadUsers(${body.current_page - 1})">‹</button>
        <small>Page ${body.current_page} of ${body.last_page}</small>
        <button class="btn btn-sm btn-outline-secondary" ${body.current_page >= body.last_page ? 'disabled' : ''}
                onclick="loadUsers(${body.current_page + 1})">›</button>`;
}

document.getElementById('user_search').addEventListener('input', function () {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadUsers(1), 350);
});

async function selectUser(id) {
    currentUserId = id;
    document.querySelectorAll('.user-row').forEach(r =>
        r.classList.toggle('table-active', parseInt(r.dataset.id) === id));

    const res  = await fetch(`${ROUTE_USER_PERMS}/${id}`, { headers: { Accept: 'application/json' } });
    const data = await res.json();

    document.getElementById('panel_user_name').textContent  = `${data.user.fname} ${data.user.lname}`;
    document.getElementById('panel_user_level').textContent = data.user.level;

    renderPermissions(data.permissions);

    document.getElementById('permission_panel').style.display = '';
    document.getElementById('permission_placeholder').style.display = 'none';
}

function renderPermissions(grouped) {
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

    const cards = Object.entries(grouped).map(([module, perms]) => {
        const info = MODULE_INFO[module] || { label: module, icon: 'bi-gear' };
        return `
            <div class="col">
                <div class="card h-100">
                    <div class="card-header fw-semibold">
                        <i class="bi ${info.icon} me-1"></i> ${info.label}
                    </div>
                    <div class="card-body py-2">
                        ${perms.map(p => renderPermRow(p)).join('')}
                    </div>
                </div>
            </div>`;
    }).join('');

    document.getElementById('permissions_by_module').innerHTML =
        `<div class="row row-cols-md-2 g-3">${cards}</div>`;
}

function renderPermRow(p) {
    let action;
    if (p.effective) {
        action = p.granted ? 'reset' : 'revoke';
    } else {
        action = p.revoked ? 'reset' : 'grant';
    }

    const hint = p.from_level && !p.revoked && !p.granted
        ? '<small class="text-muted ms-2">(role default)</small>'
        : p.granted ? '<small class="text-success ms-2">(custom grant)</small>'
        : p.revoked ? '<small class="text-danger ms-2">(revoked)</small>'
        : '';

    return `
        <div class="d-flex align-items-center justify-content-between py-2 perm-row">
            <span>${p.description}${hint}</span>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch"
                       style="cursor:pointer; width:2.5em; height:1.25em;"
                       ${p.effective ? 'checked' : ''}
                       onchange="setPermission('${p.code}', '${action}')">
            </div>
        </div>`;
}

async function setPermission(code, type) {
    await fetch(`${ROUTE_USER_PERMS}/${currentUserId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF,
        },
        body: JSON.stringify({ code, type }),
    });
    await selectUser(currentUserId);
}

document.getElementById('btn_reset_all').addEventListener('click', async function () {
    if (!currentUserId) return;
    if (!confirm('Reset all custom permissions for this user to level defaults?')) return;
    await fetch(`${ROUTE_USER_PERMS}/${currentUserId}/reset`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
    });
    await selectUser(currentUserId);
});

loadUsers();
</script>
@endsection
