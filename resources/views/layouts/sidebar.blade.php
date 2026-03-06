<aside id="sidebar" class="sidebar" role="navigation" aria-label="Main sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">
        <li class="nav-heading">General</li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('profile') ? '' : 'collapsed' }}" href="{{ route('profile') }}">
                <i class="bi bi-person"></i>
                <span>PDS</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('saln') ? '' : 'collapsed' }}" href="{{ route('saln') }}">
                <i class="bi bi-file-earmark-text"></i>
                <span>SALN</span>
            </a>
        </li>

        {{-- SALN ADMIN (HR + admin) --}}
        @if(in_array(session('access_rights'), ['hr','HRH','admin']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('saln.admin.*') ? '' : 'collapsed' }}"
                   href="{{ route('saln.admin.index') }}">
                    <i class="bi bi-file-earmark-lock"></i>
                    <span>SALN Admin</span>
                </a>
            </li>
        @endif

        <li class="nav-heading">DTR Management</li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('dtr') ? '' : 'collapsed' }}" href="{{ route('dtr') }}">
                <i class="bi bi-calendar-check"></i>
                <span>DTR Viewer</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('pass_slip') ? '' : 'collapsed' }}" href="{{ route('pass_slip') }}">
                <i class="bi bi-calendar-check"></i>
                <span>Pass Slips</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('hospital_order_file') ? '' : 'collapsed' }}" href="{{ route('hospital_order_file') }}">
                <i class="bi bi-hospital"></i>
                <span>Hospital Order Filing</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('overtime_file') ? '' : 'collapsed' }}" href="{{ route('overtime_file') }}">
                <i class="bi bi-clock-history"></i>
                <span>Overtime Filing</span>
            </a>
        </li>
        @if(in_array(session('access_rights'), ['admin'], true) || session('is_section_head') || session('is_division_head'))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hospital_order_approval') ? '' : 'collapsed' }}" href="{{ route('hospital_order_approval') }}">
                    <i class="bi bi-check2-square"></i>
                    <span>Hospital Order Approval</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('overtime_approval') ? '' : 'collapsed' }}" href="{{ route('overtime_approval') }}">
                    <i class="bi bi-check2-square"></i>
                    <span>Overtime Approval</span>
                </a>
            </li>
        @endif

        {{-- ✅ PASS SLIP APPROVAL MODULE (SH, DH, HRH, HR, admin) --}}
        @if(false)
            @if(in_array(session('access_rights'), ['SH', 'DH', 'HRH', 'hr', 'admin']))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pass-slip.approver.index') ? '' : 'collapsed' }}"
                    href="{{ route('pass-slip.approver.index') }}">
                        <i class="bi bi-check2-square"></i>
                        <span>Pass Slip Approval</span>
                    </a>
                </li>
            @endif
        @endif
        @if(in_array(session('access_rights'), ['admin']) || session('is_section_head') || session('is_division_head'))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('employee_dtr') ? '' : 'collapsed' }}"
                   href="{{ route('employee_dtr') }}">
                    <i class="bi bi-people"></i>
                    <span>Employee DTR</span>
                </a>
            </li>
        @endif

        {{-- SCHEDULE MANAGEMENT (only HRH + admin) --}}
        @if(in_array(session('access_rights'), ['admin','schedule']))
            <li class="nav-heading">Schedule Management</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('schedule') ? '' : 'collapsed' }}"
                   href="{{ route('schedule') }}">
                    <i class="bi bi-clock-history"></i>
                    <span>Manage Schedule</span>
                </a>
            </li>
            @if(in_array(session('access_rights'), ['admin']))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('schedule_master_list') ? '' : 'collapsed' }}"
                       href="{{ route('schedule_master_list') }}">
                        <i class="bi bi-list-task"></i>
                        <span>Schedule Master List</span>
                    </a>
                </li>
            @endif
            @if(in_array(session('access_rights'), ['admin']))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('schedule_access') ? '' : 'collapsed' }}"
                       href="{{ route('schedule_access') }}">
                        <i class="bi bi-sliders"></i>
                        <span>Schedule Access</span>
                    </a>
                </li>
            @endif
        @endif

        @if(in_array(session('access_rights'), ['admin']))
            <li class="nav-item d-none">
                <a class="nav-link collapsed" href="{{ route('promotion_applied') }}">
                    <i class="bi bi-award"></i>
                    <span>Position Applied</span>
                </a>
            </li>
        @endif

        {{-- CREDITS MANAGEMENT (only HRH + admin) --}}
        @if(in_array(session('access_rights'), ['admin']))
            <li class="nav-heading">Credits Management</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('credit_management') ? '' : 'collapsed' }}"
                   href="{{ route('credit_management') }}">
                    <i class="bi bi-piggy-bank"></i>
                    <span>Credits Management</span>
                </a>
            </li>
        @endif

        
        {{-- LEAVE MANAGEMENT --}}
        <li class="nav-heading">Leave Management</li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('leave_application') ? '' : 'collapsed' }}"
               href="{{ route('leave_application') }}">
                <i class="bi bi-calendar-plus"></i>
                <span>Leave Application</span>
            </a>
        </li>
        @if(in_array(session('access_rights'), ['admin', 'HRH', 'SH', 'DH', 'hr']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('leave_approval') ? '' : 'collapsed' }}"
                   href="{{ route('leave_approval') }}">
                    <i class="bi bi-calendar-check-fill"></i>
                    <span>Leave Approval</span>
                </a>
            </li>
        @endif

        {{-- HR MANAGEMENT (hr + HRH + admin) --}}
        @if(in_array(session('access_rights'), ['hr','HRH','admin']))
            <li class="nav-heading">HR Management</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('employee') ? '' : 'collapsed' }}"
                   href="{{ route('employee') }}">
                    <i class="bi bi-person-badge"></i>
                    <span>Employee</span>
                </a>
            </li>

            {{-- PAYROLL MANAGEMENT --}}
            @if (false)
                <li class="nav-heading">Payroll Management</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payroll') ? '' : 'collapsed' }}"
                   href="{{ route('payroll') }}">
                    <i class="bi bi-cash-stack"></i>
                    <span>Payroll</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="#">
                    <i class="bi bi-sliders"></i>
                    <span>
                        Adjustments
                        <span class="badge bg-danger text-white ms-1">Soon</span>
                    </span>
                </a>
            </li>
            @endif
            
        @endif

        @if(in_array(session('access_rights'), ['admin', 'calendar']))
            {{-- SETTINGS --}}
            <li class="nav-heading">Settings</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('calendar') ? '' : 'collapsed' }}"
                   href="{{ route('calendar') }}">
                    <i class="bi bi-calendar"></i>
                    <span>Calendar</span>
                </a>
            </li>
            @if(in_array(session('access_rights'), ['admin']))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('hospital_order_create') ? '' : 'collapsed' }}" href="{{ route('hospital_order_create') }}">
                        <i class="bi bi-hospital"></i>
                        <span>Hospital Order Create</span>
                    </a>
                </li>
            @endif
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('timetable') ? '' : 'collapsed' }}"
                   href="{{ route('timetable') }}">
                    <i class="bi bi-clock"></i>
                    <span>Timetable Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('leave_management') ? '' : 'collapsed' }}"
                   href="{{ route('leave_management') }}">
                    <i class="bi bi-calendar-event"></i>
                    <span>Leave Settings</span>
                </a>
            </li>
        @endif

        {{-- MOBILE DTR --}}
        <li class="nav-heading">Mobile DTR</li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="{{ route('download.latest') }}">
                <i class="bi bi-android2"></i>
                <span>Download APK</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="{{ route('download.old_version') }}">
                <i class="bi bi-android"></i>
                <span>Download Old Version</span>
            </a>
        </li>
    </ul>
</aside>
