<aside id="sidebar" class="sidebar" role="navigation" aria-label="Main sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">
        {{-- GENERAL --}}
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
        @if(in_array(session('access_rights'), ['hr','HRH','admin']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('saln.admin.*') ? '' : 'collapsed' }}"
                   href="{{ route('saln.admin.index') }}">
                    <i class="bi bi-file-earmark-lock"></i>
                    <span>SALN Admin</span>
                </a>
            </li>
        @endif

        {{-- DTR MANAGEMENT --}}
        <li class="nav-heading">DTR Management</li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('dtr') ? '' : 'collapsed' }}" href="{{ route('dtr') }}">
                <i class="bi bi-calendar-check"></i>
                <span>DTR Viewer</span>
            </a>
        </li>
        @if(in_array(strtolower((string) session('access_rights')), ['admin', 'schedule', 'dh'], true) || session('is_section_head') || session('is_division_head'))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('employee_dtr') ? '' : 'collapsed' }}"
                   href="{{ route('employee_dtr') }}">
                    <i class="bi bi-people"></i>
                    <span>Employee DTR</span>
                </a>
            </li>
        @endif

        {{-- PASS SLIP MANAGEMENT --}}
        <li class="nav-heading">Pass Slip Management</li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('pass_slip') ? '' : 'collapsed' }}" href="{{ route('pass_slip') }}">
                <i class="bi bi-calendar-check"></i>
                <span>Pass Slips</span>
            </a>
        </li>
        @if(in_array(session('access_rights'), ['SH', 'DH', 'HRH', 'hr', 'admin']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('pass-slip.approver.index') ? '' : 'collapsed' }}"
                   href="{{ route('pass-slip.approver.index') }}">
                    <i class="bi bi-check2-square"></i>
                    <span>Pass Slip Approval</span>
                </a>
            </li>
        @endif

        {{-- HOSPITAL ORDER MANAGEMENT --}}
        <li class="nav-heading">Hospital Order Management</li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('hospital_order_file') ? '' : 'collapsed' }}" href="{{ route('hospital_order_file') }}">
                <i class="bi bi-hospital"></i>
                <span>Hospital Order Filing</span>
            </a>
        </li>
        @if(in_array(session('access_rights'), ['admin'], true) || session('is_section_head') || session('is_division_head'))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hospital_order_approval') ? '' : 'collapsed' }}" href="{{ route('hospital_order_approval') }}">
                    <i class="bi bi-check2-square"></i>
                    <span>Hospital Order Approval</span>
                </a>
            </li>
        @endif

        {{-- OVERTIME MANAGEMENT --}}
        <li class="nav-heading">Overtime Management</li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('overtime_file') ? '' : 'collapsed' }}" href="{{ route('overtime_file') }}">
                <i class="bi bi-clock-history"></i>
                <span>Overtime Filing</span>
            </a>
        </li>
        @if(in_array(session('access_rights'), ['admin'], true) || session('is_section_head') || session('is_division_head'))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('overtime_approval') ? '' : 'collapsed' }}" href="{{ route('overtime_approval') }}">
                    <i class="bi bi-check2-square"></i>
                    <span>Overtime Approval</span>
                </a>
            </li>
        @endif

        {{-- SCHEDULE MANAGEMENT --}}
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

        {{-- CREDITS MANAGEMENT --}}
        @if(in_array(session('access_rights'), ['admin']) && false)
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
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('leave_cancellation') ? '' : 'collapsed' }}"
               href="{{ route('leave_cancellation') }}">
                <i class="bi bi-calendar-x"></i>
                <span>Leave Cancellation</span>
            </a>
        </li>
        @if(in_array(session('access_rights'), ['admin', 'HRH', 'SH', 'DH', 'hr']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cancellation_approval') ? '' : 'collapsed' }}"
                   href="{{ route('cancellation_approval') }}">
                    <i class="bi bi-check2-circle"></i>
                    <span>Cancellation Approval</span>
                </a>
            </li>
        @endif
        @if(in_array(session('access_rights'), ['admin', 'HRH', 'hr']))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin_cancellations') ? '' : 'collapsed' }}"
                   href="{{ route('admin_cancellations') }}">
                    <i class="bi bi-list-check"></i>
                    <span>All Cancellations</span>
                </a>
            </li>
        @endif

        {{-- HR MANAGEMENT --}}
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
            <li class="nav-heading">Payroll Management</li>
             <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payroll') ? '' : 'collapsed' }}"
                   href="{{ route('payroll') }}">
                    <i class="bi bi-cash-stack"></i>
                    <span>Payroll</span>
                </a>
            </li> <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payroll') ? '' : 'collapsed' }}"
                   href="{{ route('payroll_settings') }}">
                    <i class="bi bi-list-check"></i>
                    <span>Payroll Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payroll_components') ? '' : 'collapsed' }}"
                   href="{{ route('payroll_components') }}">
                    <i class="bi bi-list-check"></i>
                    <span>Earnings/Deductions</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payroll_tax_rules') ? '' : 'collapsed' }}"
                   href="{{ route('payroll_tax_rules') }}">
                    <i class="bi bi-percent"></i>
                    <span>Taxation</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cutoff_summaries') ? '' : 'collapsed' }}"
                   href="{{ route('cutoff_summaries') }}">
                    <i class="bi bi-calendar-range"></i>
                    <span>Cutoff Summary</span>
                </a>
            </li>
        @endif

        {{-- REPORTS --}}
        @if(in_array(session('access_rights'), ['hr','HRH','admin']))
            <li class="nav-heading">Reports</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('report.quarterly_leave_balance') ? '' : 'collapsed' }}"
                   href="{{ route('report.quarterly_leave_balance') }}">
                    <i class="bi bi-calendar3"></i>
                    <span>Quarterly Leave Balances</span>
                </a>
            </li>
        @endif

        {{-- SETTINGS --}}
        @if(in_array(session('access_rights'), ['admin', 'calendar']))
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
                <a class="nav-link {{ request()->routeIs('leave_management') ? '' : 'collapsed' }}"
                   href="{{ route('leave_management') }}">
                    <i class="bi bi-calendar-event"></i>
                    <span>Leave Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('bulk-leave-credits.*') ? '' : 'collapsed' }}"
                   href="{{ route('bulk-leave-credits.index') }}">
                    <i class="bi bi-credit-card-fill"></i>
                    <span>Bulk Leave Credits</span>
                </a>
            </li>
        @endif

        {{-- MOBILE DTR --}}
        @if(false)
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
        @endif
    </ul>
</aside>
