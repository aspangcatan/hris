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
        @permission('saln.view_own')
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('saln') ? '' : 'collapsed' }}" href="{{ route('saln') }}">
                <i class="bi bi-file-earmark-text"></i>
                <span>SALN</span>
            </a>
        </li>
        @endpermission
        @permission('saln.admin')
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('saln.admin.*') ? '' : 'collapsed' }}"
                href="{{ route('saln.admin.index') }}">
                <i class="bi bi-file-earmark-lock"></i>
                <span>SALN Admin</span>
            </a>
        </li>
        @endpermission
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('reorientation') ? '' : 'collapsed' }}" href="{{ route('reorientation') }}">
                <i class="bi bi-journal-bookmark"></i>
                <span>Re-Orientation</span>
            </a>
        </li>

        {{-- DTR MANAGEMENT --}}
        <li class="nav-heading">DTR Management</li>
        @permission('dtr.view_own')
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('dtr') ? '' : 'collapsed' }}" href="{{ route('dtr') }}">
                <i class="bi bi-calendar-check"></i>
                <span>DTR Viewer</span>
            </a>
        </li>
        @endpermission
        @permission('dtr.view_others')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('employee_dtr') ? '' : 'collapsed' }}"
                    href="{{ route('employee_dtr') }}">
                    <i class="bi bi-people"></i>
                    <span>Employee DTR</span>
                </a>
            </li>
        @endpermission

        {{-- PASS SLIP MANAGEMENT --}}
        @if(permission_check('passslip.file') || permission_check('passslip.approve'))
            <li class="nav-heading">Pass Slip Management</li>
            @permission('passslip.file')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('pass_slip') ? '' : 'collapsed' }}"
                    href="{{ route('pass_slip') }}">
                    <i class="bi bi-calendar-check"></i>
                    <span>Pass Slips</span>
                </a>
            </li>
            @endpermission
            @permission('passslip.approve')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('pass-slip.approver.index') ? '' : 'collapsed' }}"
                    href="{{ route('pass-slip.approver.index') }}">
                    <i class="bi bi-check2-square"></i>
                    <span>Pass Slip Approval</span>
                </a>
            </li>
            @endpermission
        @endif

        {{-- HOSPITAL ORDER MANAGEMENT --}}
        @if(permission_check('hospital_order.file') || permission_check('hospital_order.approve'))
            <li class="nav-heading">Hospital Order Management</li>
            @permission('hospital_order.file')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hospital_order_file') ? '' : 'collapsed' }}"
                    href="{{ route('hospital_order_file') }}">
                    <i class="bi bi-hospital"></i>
                    <span>Hospital Order Filing</span>
                </a>
            </li>
            @endpermission
            @permission('hospital_order.approve')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hospital_order_approval') ? '' : 'collapsed' }}"
                    href="{{ route('hospital_order_approval') }}">
                    <i class="bi bi-check2-square"></i>
                    <span>Hospital Order Approval</span>
                </a>
            </li>
            @endpermission
        @endif

        {{-- OVERTIME MANAGEMENT (hidden for now) --}}
        @if(false && (permission_check('overtime.file') || permission_check('overtime.approve')))
            <li class="nav-heading">Overtime Management</li>
            @permission('overtime.file')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('overtime_file') ? '' : 'collapsed' }}"
                    href="{{ route('overtime_file') }}">
                    <i class="bi bi-clock-history"></i>
                    <span>Overtime Filing</span>
                </a>
            </li>
            @endpermission
            @permission('overtime.approve')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('overtime_approval') ? '' : 'collapsed' }}"
                    href="{{ route('overtime_approval') }}">
                    <i class="bi bi-check2-square"></i>
                    <span>Overtime Approval</span>
                </a>
            </li>
            @endpermission
        @endif

        {{-- SCHEDULE MANAGEMENT --}}
        @if(permission_check('schedule.manage') || permission_check('schedule.view'))
            <li class="nav-heading">Schedule Management</li>
            @permission('schedule.manage')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('schedule') ? '' : 'collapsed' }}" href="{{ route('schedule') }}">
                    <i class="bi bi-clock-history"></i>
                    <span>Manage Schedule</span>
                </a>
            </li>
            @endpermission
            @if(!permission_check('schedule.manage') && permission_check('schedule.view'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('schedule') ? '' : 'collapsed' }}" href="{{ route('schedule') }}">
                        <i class="bi bi-clock-history"></i>
                        <span>View Schedule</span>
                    </a>
                </li>
            @endif
            {{-- Saving/deleting master list templates is admin-only (ScheduleController), so the link is too. --}}
            @if(strtolower((string) session('access_rights')) === 'admin')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('schedule_master_list') ? '' : 'collapsed' }}"
                        href="{{ route('schedule_master_list') }}">
                        <i class="bi bi-list-task"></i>
                        <span>Schedule Master List</span>
                    </a>
                </li>
            @endif
            {{-- Schedule Access: who manages which sections. --}}
            @permission('schedule.access')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('schedule_access') ? '' : 'collapsed' }}"
                        href="{{ route('schedule_access') }}">
                        <i class="bi bi-sliders"></i>
                        <span>Schedule Access</span>
                    </a>
                </li>
            @endpermission
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
        @if(true)
            @if(permission_check('leave.file') || permission_check('leave.approve') || permission_check('leave.approve_hr'))
                <li class="nav-heading">Leave Management</li>
                @permission('leave.file')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('leave_application') ? '' : 'collapsed' }}"
                        href="{{ route('leave_application') }}">
                        <i class="bi bi-calendar-plus"></i>
                        <span>Leave Application</span>
                    </a>
                </li>
                @endpermission
                @permission('leave.approve')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('leave_approval') ? '' : 'collapsed' }}"
                        href="{{ route('leave_approval') }}">
                        <i class="bi bi-calendar-check-fill"></i>
                        <span>Leave Approval</span>
                    </a>
                </li>
                @endpermission
                @permission('leave.file')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('leave_cancellation') ? '' : 'collapsed' }}"
                        href="{{ route('leave_cancellation') }}">
                        <i class="bi bi-calendar-x"></i>
                        <span>Leave Cancellation</span>
                    </a>
                </li>
                @endpermission
                @permission('leave.approve')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('cancellation_approval') ? '' : 'collapsed' }}"
                        href="{{ route('cancellation_approval') }}">
                        <i class="bi bi-check2-circle"></i>
                        <span>Cancellation Approval</span>
                    </a>
                </li>
                @endpermission
                @permission('leave.approve_hr')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin_cancellations') ? '' : 'collapsed' }}"
                            href="{{ route('admin_cancellations') }}">
                            <i class="bi bi-list-check"></i>
                            <span>All Cancellations</span>
                        </a>
                    </li>
                @endpermission
            @endif
        @endif
        {{-- HR MANAGEMENT --}}
        @if(permission_check('employee.view') || permission_check('promotion.manage'))
        <li class="nav-heading">HR Management</li>
        @permission('employee.view')
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('employee') ? '' : 'collapsed' }}" href="{{ route('employee') }}">
                <i class="bi bi-person-badge"></i>
                <span>Employee</span>
            </a>
        </li>
        @endpermission
        @permission('promotion.manage')
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('promotion_applied') ? '' : 'collapsed' }}" href="{{ route('promotion_applied') }}">
                <i class="bi bi-award"></i>
                <span>Position Applied</span>
            </a>
        </li>
        @endpermission
        @endif

        {{-- PAYROLL MANAGEMENT --}}
        @if(false)
        @if(permission_check('payroll.view') || permission_check('payroll.settings') || permission_check('payroll.manage'))
            <li class="nav-heading">Payroll Management</li>
            @permission('payroll.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payroll') ? '' : 'collapsed' }}" href="{{ route('payroll') }}">
                    <i class="bi bi-cash-stack"></i>
                    <span>Payroll</span>
                </a>
            </li>
            @endpermission
            @permission('payroll.settings')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payroll') ? '' : 'collapsed' }}"
                    href="{{ route('payroll_settings') }}">
                    <i class="bi bi-list-check"></i>
                    <span>Payroll Settings</span>
                </a>
            </li>
            @endpermission
            @permission('payroll.manage')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payroll_components') ? '' : 'collapsed' }}"
                    href="{{ route('payroll_components') }}">
                    <i class="bi bi-list-check"></i>
                    <span>Earnings/Deductions</span>
                </a>
            </li>
            @endpermission
            @permission('payroll.settings')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payroll_tax_rules') ? '' : 'collapsed' }}"
                    href="{{ route('payroll_tax_rules') }}">
                    <i class="bi bi-percent"></i>
                    <span>Taxation</span>
                </a>
            </li>
            @endpermission
            @permission('payroll.settings')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payroll_assignatories') ? '' : 'collapsed' }}"
                    href="{{ route('payroll_assignatories') }}">
                    <i class="bi bi-person-check"></i>
                    <span>Payroll Assignatories</span>
                </a>
            </li>
            @endpermission
            @permission('payroll.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cutoff_summaries') ? '' : 'collapsed' }}"
                    href="{{ route('cutoff_summaries') }}">
                    <i class="bi bi-calendar-range"></i>
                    <span>Cutoff Summary</span>
                </a>
            </li>
            @endpermission
        @endif
        @endif

        {{-- REPORTS (hidden for now) --}}
        @if(false)
        @permission('reports.quarterly_leave')
        <li class="nav-heading">Reports</li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('report.quarterly_leave_balance') ? '' : 'collapsed' }}"
                href="{{ route('report.quarterly_leave_balance') }}">
                <i class="bi bi-calendar3"></i>
                <span>Quarterly Leave Balances</span>
            </a>
        </li>
        @endpermission
        @endif

        {{-- SETTINGS --}}
        {{-- Leave settings hidden for now; restore "|| permission_check('leave.manage_types') || permission_check('leave.manage_credits')" when re-enabled --}}
        @php($isAdmin = strtolower((string) session('access_rights')) === 'admin')
        @if(permission_check('calendar.manage') || permission_check('hospital_order.create') || $isAdmin)
            <li class="nav-heading">Settings</li>
            @permission('calendar.manage')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('calendar') ? '' : 'collapsed' }}" href="{{ route('calendar') }}">
                        <i class="bi bi-calendar"></i>
                        <span>Calendar</span>
                    </a>
                </li>
            @endpermission
            @permission('hospital_order.create')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('hospital_order_create') ? '' : 'collapsed' }}"
                        href="{{ route('hospital_order_create') }}">
                        <i class="bi bi-hospital"></i>
                        <span>Hospital Order Create</span>
                    </a>
                </li>
            @endpermission
            {{-- Access Rights / Permission Levels stay admin-only (their controllers check the admin role too). --}}
            @if($isAdmin)
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.permissions') ? '' : 'collapsed' }}"
                        href="{{ route('admin.permissions') }}">
                        <i class="bi bi-shield-lock"></i>
                        <span>Access Rights</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.permission_levels') ? '' : 'collapsed' }}"
                        href="{{ route('admin.permission_levels') }}">
                        <i class="bi bi-diagram-3"></i>
                        <span>Permission Levels</span>
                    </a>
                </li>
            @endif
            @if(false)
            @permission('leave.manage_types')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('leave_management') ? '' : 'collapsed' }}"
                    href="{{ route('leave_management') }}">
                    <i class="bi bi-calendar-event"></i>
                    <span>Leave Settings</span>
                </a>
            </li>
            @endpermission
            @permission('leave.manage_credits')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('bulk-leave-credits.*') ? '' : 'collapsed' }}"
                    href="{{ route('bulk-leave-credits.index') }}">
                    <i class="bi bi-credit-card-fill"></i>
                    <span>Bulk Leave Credits</span>
                </a>
            </li>
            @endpermission
            @endif
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