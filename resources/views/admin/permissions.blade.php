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

                    <hr>
                    <h6>Recent Changes</h6>
                    <div id="audit_log" style="max-height:200px; overflow-y:auto; font-size:.85rem;">
                        <p class="text-muted">No changes yet.</p>
                    </div>
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

@push('scripts')
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
    renderAuditLog(data.audit_log);

    document.getElementById('permission_panel').style.display = '';
    document.getElementById('permission_placeholder').style.display = 'none';
}

function renderPermissions(grouped) {
    const MODULE_LABELS = {
        dtr: 'DTR', employee: 'Employee', leave: 'Leave', payroll: 'Payroll',
        overtime: 'Overtime', passslip: 'Pass Slip', schedule: 'Schedule',
        saln: 'SALN', hospital_order: 'Hospital Order', reports: 'Reports',
    };

    const html = Object.entries(grouped).map(([module, perms]) => `
        <div class="mb-3">
            <div class="fw-semibold text-uppercase text-muted small mb-1">
                ${MODULE_LABELS[module] || module}
            </div>
            ${perms.map(p => renderPermRow(p)).join('')}
        </div>`).join('');

    document.getElementById('permissions_by_module').innerHTML = html;
}

function renderPermRow(p) {
    let badge, btn;
    if (p.revoked) {
        badge = '<span class="badge bg-danger ms-2">Revoked</span>';
        btn   = `<button class="btn btn-sm btn-outline-secondary" onclick="setPermission('${p.code}','reset')">Restore</button>`;
    } else if (p.granted) {
        badge = '<span class="badge bg-success ms-2">Granted</span>';
        btn   = `<button class="btn btn-sm btn-outline-secondary" onclick="setPermission('${p.code}','reset')">Remove</button>`;
    } else if (p.from_level) {
        badge = '<span class="badge bg-light text-dark border ms-2">Level Default</span>';
        btn   = `<button class="btn btn-sm btn-outline-danger" onclick="setPermission('${p.code}','revoke')">Revoke</button>`;
    } else {
        badge = '<span class="badge bg-light text-muted border ms-2">No Access</span>';
        btn   = `<button class="btn btn-sm btn-outline-success" onclick="setPermission('${p.code}','grant')">Grant</button>`;
    }

    return `
        <div class="d-flex align-items-center justify-content-between py-1 border-bottom">
            <span>${p.description}${badge}</span>
            ${btn}
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

function renderAuditLog(logs) {
    const el = document.getElementById('audit_log');
    if (!logs.length) {
        el.innerHTML = '<p class="text-muted mb-0">No changes yet.</p>';
        return;
    }
    el.innerHTML = logs.map(l => `
        <div class="border-bottom py-1">
            <span class="text-capitalize">${l.action}</span>
            <span class="text-muted ms-2">by ${l.performed_by}</span>
            <span class="text-muted float-end">${moment(l.created_at).format('MM/DD/YYYY HH:mm')}</span>
        </div>`).join('');
}

document.addEventListener('DOMContentLoaded', () => loadUsers());
</script>
@endpush
