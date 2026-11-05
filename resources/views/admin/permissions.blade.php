@extends('master')

@section('content')
<style>
    /* Access Rights - uses the theme tokens from public/css/app-theme.css */
    .ar-layout { display: grid; grid-template-columns: minmax(280px, 340px) 1fr; gap: 1.25rem; align-items: start; }

    .ar-card { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: 0 1px 2px rgba(19,27,46,.04); }
    .ar-bar { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--line); }
    .ar-card-title { margin: 0; font: 700 1rem/1.4 var(--font-head); color: var(--on-surface); display: flex; align-items: center; gap: .5rem; }
    .ar-card-title i { color: var(--secondary); }

    /* Employee list */
    .ar-list-card { position: sticky; top: 84px; }
    .ar-search { position: relative; padding: .875rem 1.25rem; border-bottom: 1px solid var(--line); }
    .ar-search i { position: absolute; left: 2.05rem; top: 50%; transform: translateY(-50%); color: var(--outline); pointer-events: none; }
    .ar-search input { padding-left: 2.3rem; }
    .ar-users { max-height: calc(100vh - 330px); min-height: 220px; overflow-y: auto; padding: .375rem; }
    .ar-user { display: flex; align-items: center; gap: .75rem; width: 100%; padding: .55rem .75rem; border: 0; border-radius: .625rem; background: transparent; text-align: left; color: var(--on-surface); transition: background .12s; }
    .ar-user:hover { background: var(--surface-low); }
    .ar-user.active { background: var(--primary-fixed, #dde1ff); }
    .ar-user.active .ar-user-name { color: var(--primary); }
    .ar-user-name { flex: 1; min-width: 0; font-weight: 600; font-size: .9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ar-avatar { flex: none; width: 2.25rem; height: 2.25rem; border-radius: 50%; display: grid; place-items: center; font: 700 .8rem/1 var(--font-head); color: var(--primary); background: var(--surface-low); border: 1px solid var(--line); }
    .ar-avatar.lg { width: 3.25rem; height: 3.25rem; font-size: 1.1rem; background: var(--primary); color: #fff; border: 0; }
    .ar-list-foot { display: flex; align-items: center; justify-content: space-between; padding: .75rem 1.25rem; border-top: 1px solid var(--line); font-size: .8rem; color: var(--outline); }
    .ar-list-foot .btn { width: 2rem; height: 2rem; padding: 0; display: inline-grid; place-items: center; }
    .ar-muted { padding: 2rem 1rem; text-align: center; color: var(--outline); font-size: .875rem; }

    /* Level chip */
    .ar-level { flex: none; display: inline-flex; align-items: center; padding: .15rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; background: var(--surface-low); color: var(--on-surface-variant); border: 1px solid var(--line); }
    .ar-level.admin { background: #ffdad6; color: #93000a; border-color: #ffb4ab; }
    .ar-level.it    { background: #e0e0ff; color: #00236f; border-color: #c3c6ff; }
    .ar-level.hr    { background: #cdefe9; color: #005049; border-color: #9fd8cf; }
    .ar-level.dh, .ar-level.sh { background: #fff1d6; color: #6b4a00; border-color: #f2d48f; }

    /* Empty state */
    .ar-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 4rem 1.5rem; gap: .5rem; }
    .ar-empty-icon { width: 4rem; height: 4rem; border-radius: 1rem; display: grid; place-items: center; font-size: 1.75rem; background: var(--surface-low); color: var(--primary); margin-bottom: .5rem; }
    .ar-empty h5 { margin: 0; font: 700 1.05rem/1.4 var(--font-head); color: var(--on-surface); }
    .ar-empty p { margin: 0; color: var(--outline); max-width: 26rem; font-size: .9rem; }

    /* Selected user summary */
    .ar-hero { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: 1.25rem; }
    .ar-hero-name { margin: 0; font: 700 1.25rem/1.3 var(--font-head); color: var(--primary); }
    .ar-stats { display: flex; flex-wrap: wrap; gap: .5rem; margin-left: auto; }
    .ar-stat { min-width: 6.5rem; padding: .5rem .875rem; border-radius: .75rem; background: var(--surface-low); border: 1px solid var(--line); }
    .ar-stat b { display: block; font: 700 1.15rem/1.2 var(--font-head); color: var(--on-surface); }
    .ar-stat span { font-size: .72rem; color: var(--outline); text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
    .ar-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1.25rem; border-top: 1px solid var(--line); background: var(--surface-low); border-radius: 0 0 var(--radius) var(--radius); }
    .ar-legend { display: flex; flex-wrap: wrap; gap: .4rem; font-size: .78rem; color: var(--on-surface-variant); }

    /* State pills */
    .ar-pill { display: inline-flex; align-items: center; gap: .3rem; padding: .1rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 600; white-space: nowrap; }
    .ar-pill::before { content: ""; width: .4rem; height: .4rem; border-radius: 50%; background: currentColor; }
    .ar-pill.default { background: var(--surface-low); color: var(--on-surface-variant); }
    .ar-pill.granted { background: #cdefe9; color: #005049; }
    .ar-pill.revoked { background: #ffdad6; color: #93000a; }
    .ar-pill.off     { background: transparent; color: var(--outline); border: 1px dashed var(--line); }

    /* Module cards */
    .ar-modules { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(320px, 100%), 1fr)); gap: 1rem; margin-top: 1.25rem; }
    .ar-modbar { display: flex; align-items: center; gap: .65rem; padding: .875rem 1.125rem; border-bottom: 1px solid var(--line); }
    .ar-module-icon { width: 2rem; height: 2rem; border-radius: .5rem; display: grid; place-items: center; background: var(--surface-low); color: var(--primary); }
    .ar-module-name { flex: 1; font: 700 .95rem/1.3 var(--font-head); color: var(--on-surface); }
    .ar-module-count { font-size: .75rem; font-weight: 600; color: var(--outline); }
    .ar-perm { display: flex; align-items: center; gap: .75rem; padding: .7rem 1.125rem; border-left: 3px solid transparent; }
    .ar-perm + .ar-perm { border-top: 1px solid var(--line); }
    .ar-perm.is-granted { border-left-color: var(--secondary); }
    .ar-perm.is-revoked { border-left-color: #ba1a1a; }
    .ar-perm-text { flex: 1; min-width: 0; }
    .ar-perm-desc { font-size: .875rem; font-weight: 500; color: var(--on-surface); }
    .ar-perm-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; margin-top: .2rem; }
    .ar-perm-code { font: 500 .7rem/1 ui-monospace, SFMono-Regular, Consolas, monospace; color: var(--outline); }
    .ar-perm .form-switch { margin: 0; padding-left: 2.75em; }
    .ar-perm .form-check-input { width: 2.5em; height: 1.35em; cursor: pointer; }
    .ar-perm .form-check-input:checked { background-color: var(--secondary); border-color: var(--secondary); }
    .ar-perm.is-saving { opacity: .55; pointer-events: none; }

    /* Audit log */
    .ar-log { list-style: none; margin: 0; padding: .5rem 1.25rem 1rem; }
    .ar-log li { display: flex; gap: .75rem; padding: .6rem 0; font-size: .85rem; }
    .ar-log li + li { border-top: 1px dashed var(--line); }
    .ar-log-dot { flex: none; width: .55rem; height: .55rem; margin-top: .4rem; border-radius: 50%; background: var(--outline-variant); }
    .ar-log-dot.granted { background: var(--secondary); }
    .ar-log-dot.revoked { background: #ba1a1a; }
    .ar-log-when { color: var(--outline); font-size: .75rem; }

    /* Narrow screens (kept last so they override the rules above) */
    @media (max-width: 991.98px) { .ar-layout { grid-template-columns: minmax(0, 1fr); } .ar-list-card { position: static; } .ar-users { max-height: 420px; } }
    @media (max-width: 575.98px) { .ar-stats { width: 100%; margin-left: 0; flex-wrap: nowrap; } .ar-stat { flex: 1 1 0; min-width: 0; padding: .5rem .625rem; } .ar-stat span { font-size: .62rem; } .ar-toolbar .btn { width: 100%; } }
</style>

<div class="page-head">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>Access Rights</h1>
        <p class="page-sub">Grant or revoke individual permissions on top of each employee's level defaults.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.permission_levels') }}" class="btn btn-outline-primary">
            <i class="bi bi-sliders me-1"></i> Level Defaults
        </a>
    </div>
</div>

<div class="ar-layout">
    {{-- Employees --}}
    <aside class="ar-card ar-list-card">
        <div class="ar-bar">
            <h2 class="ar-card-title"><i class="bi bi-people"></i> Employees</h2>
            <span class="ar-module-count" id="user_total"></span>
        </div>
        <div class="ar-search">
            <i class="bi bi-search"></i>
            <input type="search" id="user_search" class="form-control" placeholder="Search by first or last name" autocomplete="off">
        </div>
        <div class="ar-users" id="user_list">
            <div class="ar-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading employees…</div>
        </div>
        <div class="ar-list-foot" id="user_pagination"></div>
    </aside>

    {{-- Permissions --}}
    <section>
        <div class="ar-card" id="permission_placeholder">
            <div class="ar-empty">
                <div class="ar-empty-icon"><i class="bi bi-shield-lock"></i></div>
                <h5>No employee selected</h5>
                <p>Pick an employee from the list to see what they can access. Switches follow their level by default; changing one adds a personal override.</p>
            </div>
        </div>

        <div id="permission_panel" class="d-none">
            <div class="ar-card">
                <div class="ar-hero">
                    <div class="ar-avatar lg" id="panel_avatar">—</div>
                    <div>
                        <h2 class="ar-hero-name" id="panel_user_name">—</h2>
                        <span class="ar-level" id="panel_user_level"></span>
                    </div>
                    <div class="ar-stats">
                        <div class="ar-stat"><b id="stat_on">0</b><span>Allowed</span></div>
                        <div class="ar-stat"><b id="stat_granted">0</b><span>Extra grants</span></div>
                        <div class="ar-stat"><b id="stat_revoked">0</b><span>Revoked</span></div>
                    </div>
                </div>
                <div class="ar-toolbar">
                    <div class="ar-legend">
                        <span class="ar-pill default">Level default</span>
                        <span class="ar-pill granted">Custom grant</span>
                        <span class="ar-pill revoked">Revoked</span>
                        <span class="ar-pill off">Not allowed</span>
                    </div>
                    <button class="btn btn-sm btn-outline-danger" id="btn_reset_all">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset to level defaults
                    </button>
                </div>
            </div>

            <div class="ar-modules" id="permissions_by_module"></div>

            <div class="ar-card mt-3">
                <div class="ar-bar">
                    <h2 class="ar-card-title"><i class="bi bi-clock-history"></i> Recent changes</h2>
                    <span class="ar-module-count">Last 20</span>
                </div>
                <ul class="ar-log" id="audit_log"></ul>
            </div>
        </div>
    </section>
</div>
@endsection

@section('js')
<script>
const ROUTE_USERS      = "{{ route('admin.permissions.users') }}";
const ROUTE_USER_PERMS = "{{ url('/admin/permissions/users') }}";
const CSRF             = "{{ csrf_token() }}";

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

let currentUserId = null;
let searchTimeout = null;

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function initials(fname, lname) {
    return ((fname || '').trim().charAt(0) + (lname || '').trim().charAt(0)).toUpperCase() || '?';
}
function levelChip(level) {
    const l = String(level || 'standard').toLowerCase();
    return `<span class="ar-level ${esc(l)}">${esc(l)}</span>`;
}

async function loadUsers(page = 1) {
    const search = document.getElementById('user_search').value.trim();
    const list   = document.getElementById('user_list');
    try {
        const res  = await fetch(`${ROUTE_USERS}?page=${page}&search=${encodeURIComponent(search)}`, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error(res.status);
        const body = await res.json();

        document.getElementById('user_total').textContent = `${body.total} total`;
        list.innerHTML = body.data.length === 0
            ? '<div class="ar-muted"><i class="bi bi-search d-block fs-4 mb-1"></i>No employees match your search.</div>'
            : body.data.map(u => `
                <button type="button" class="ar-user${u.id === currentUserId ? ' active' : ''}" data-id="${u.id}" onclick="selectUser(${u.id})">
                    <span class="ar-avatar">${esc(initials(u.fname, u.lname))}</span>
                    <span class="ar-user-name">${esc(u.lname)}, ${esc(u.fname)}</span>
                    ${levelChip(u.level)}
                </button>`).join('');

        document.getElementById('user_pagination').innerHTML = `
            <button class="btn btn-outline-secondary" ${body.current_page <= 1 ? 'disabled' : ''}
                    onclick="loadUsers(${body.current_page - 1})" aria-label="Previous page"><i class="bi bi-chevron-left"></i></button>
            <span>Page ${body.current_page} of ${Math.max(body.last_page, 1)}</span>
            <button class="btn btn-outline-secondary" ${body.current_page >= body.last_page ? 'disabled' : ''}
                    onclick="loadUsers(${body.current_page + 1})" aria-label="Next page"><i class="bi bi-chevron-right"></i></button>`;
    } catch (e) {
        list.innerHTML = '<div class="ar-muted text-danger"><i class="bi bi-exclamation-triangle d-block fs-4 mb-1"></i>Could not load employees.</div>';
    }
}

document.getElementById('user_search').addEventListener('input', function () {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadUsers(1), 350);
});

async function selectUser(id, keepScroll = false) {
    currentUserId = id;
    document.querySelectorAll('.ar-user').forEach(r => r.classList.toggle('active', parseInt(r.dataset.id) === id));

    const res = await fetch(`${ROUTE_USER_PERMS}/${id}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) { toastr.error('Could not load this employee\'s permissions.', 'Error'); return; }
    const data = await res.json();

    document.getElementById('panel_avatar').textContent    = initials(data.user.fname, data.user.lname);
    document.getElementById('panel_user_name').textContent = `${data.user.fname} ${data.user.lname}`;
    const lvl = document.getElementById('panel_user_level');
    lvl.className = 'ar-level ' + String(data.user.level || 'standard').toLowerCase();
    lvl.textContent = data.user.level || 'standard';

    renderPermissions(data.permissions);
    renderAuditLog(data.audit_log || []);

    document.getElementById('permission_panel').classList.remove('d-none');
    document.getElementById('permission_placeholder').classList.add('d-none');

    // On narrow screens the panel sits below the list - bring it into view.
    if (!keepScroll && window.innerWidth < 992) {
        document.getElementById('permission_panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function permState(p) {
    if (p.revoked) return 'revoked';
    if (p.granted) return 'granted';
    if (p.from_level) return 'default';
    return 'off';
}

function renderPermissions(grouped) {
    let on = 0, granted = 0, revoked = 0;

    const cards = Object.entries(grouped).map(([module, perms]) => {
        const info = MODULE_INFO[module] || { label: module, icon: 'bi-gear' };
        const active = perms.filter(p => p.effective).length;
        on += active;
        granted += perms.filter(p => p.granted && !p.revoked).length;
        revoked += perms.filter(p => p.revoked).length;
        return `
            <div class="ar-card">
                <div class="ar-modbar">
                    <span class="ar-module-icon"><i class="bi ${esc(info.icon)}"></i></span>
                    <span class="ar-module-name">${esc(info.label)}</span>
                    <span class="ar-module-count">${active} / ${perms.length} on</span>
                </div>
                ${perms.map(renderPermRow).join('')}
            </div>`;
    }).join('');

    document.getElementById('stat_on').textContent      = on;
    document.getElementById('stat_granted').textContent = granted;
    document.getElementById('stat_revoked').textContent = revoked;
    document.getElementById('permissions_by_module').innerHTML = cards;
}

function renderPermRow(p) {
    // Same toggle semantics as before: flipping an override back clears it ("reset").
    const action = p.effective ? (p.granted ? 'reset' : 'revoke') : (p.revoked ? 'reset' : 'grant');
    const state  = permState(p);
    const label  = { default: 'Level default', granted: 'Custom grant', revoked: 'Revoked', off: 'Not allowed' }[state];
    const id     = 'perm_' + String(p.code).replace(/[^a-z0-9_]/gi, '_');

    return `
        <div class="ar-perm is-${state}">
            <label class="ar-perm-text mb-0" for="${id}">
                <div class="ar-perm-desc">${esc(p.description)}</div>
                <div class="ar-perm-meta">
                    <span class="ar-pill ${state}">${label}</span>
                    <span class="ar-perm-code">${esc(p.code)}</span>
                </div>
            </label>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="${id}"
                       ${p.effective ? 'checked' : ''}
                       onchange="setPermission(this, '${esc(p.code)}', '${action}')">
            </div>
        </div>`;
}

function renderAuditLog(rows) {
    const ul = document.getElementById('audit_log');
    if (!rows.length) {
        ul.innerHTML = '<li class="text-muted">No changes recorded for this employee yet.</li>';
        return;
    }
    ul.innerHTML = rows.map(r => {
        const kind = /^revoked/.test(r.action) ? 'revoked' : (/granted/.test(r.action) ? 'granted' : '');
        const when = r.created_at ? moment(r.created_at).format('MMM D, YYYY h:mm A') : '';
        return `
            <li>
                <span class="ar-log-dot ${kind}"></span>
                <div>
                    <div>${esc(r.action)}</div>
                    <div class="ar-log-when">${esc(r.performed_by || 'System')} · ${esc(when)}</div>
                </div>
            </li>`;
    }).join('');
}

async function setPermission(input, code, type) {
    const row = input.closest('.ar-perm');
    row.classList.add('is-saving');
    try {
        const res = await fetch(`${ROUTE_USER_PERMS}/${currentUserId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ code, type }),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(body.message || 'Could not update the permission.');
        toastr.success(body.message || 'Permission updated.');
    } catch (e) {
        toastr.error(e.message, 'Error');
    }
    await selectUser(currentUserId, true);
}

document.getElementById('btn_reset_all').addEventListener('click', async function () {
    if (!currentUserId) return;
    const ok = await Swal.fire({
        icon: 'warning',
        title: 'Reset to level defaults?',
        text: 'All custom grants and revokes for this employee will be removed.',
        showCancelButton: true,
        confirmButtonText: 'Reset',
        cancelButtonText: 'Cancel',
    });
    if (!ok.isConfirmed) return;
    const res = await fetch(`${ROUTE_USER_PERMS}/${currentUserId}/reset`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
    });
    const body = await res.json().catch(() => ({}));
    res.ok ? toastr.success(body.message || 'Permissions reset.') : toastr.error(body.message || 'Reset failed.', 'Error');
    await selectUser(currentUserId, true);
});

loadUsers();
</script>
@endsection
