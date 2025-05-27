<aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">
        <li class="nav-heading">General</li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="{{ route('profile') }}">
                <i class="bi bi-grid"></i>
                <span>PDS</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="{{ route('saln') }}">
                <i class="bi bi-grid"></i>
                <span>SALN</span>
            </a>
        </li>
        <li class="nav-heading">DTR Management</li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="{{ route('dtr') }}">
                <i class="bi bi-grid"></i>
                <span>DTR Viewer</span>
            </a>
        </li>
        @if(session('is_section_head') || session('access_rights') == 'admin')
            <li class="nav-item">
                <a class="nav-link collapsed" href="{{ route('employee_dtr') }}">
                    <i class="bi bi-grid"></i>
                    <span>Employee DTR</span>
                </a>
            </li>
        @endif
        @if(session('access_rights') == 'hr' || session('access_rights') == 'admin')
            <li class="nav-heading">Schedule Management</li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="{{ route('schedule') }}">
                    <i class="bi bi-grid"></i>
                    <span>Manage Schedule</span>
                </a>
            </li>
            <li class="nav-item d-none">
                <a class="nav-link collapsed" href="{{ route('promotion_applied') }}">
                    <i class="bi bi-grid"></i>
                    <span>Position Applied</span>
                </a>
            </li>
            <li class="nav-heading">Credits Management</li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="{{ route('credit_management') }}">
                    <i class="bi bi-grid"></i>
                    <span>Credits Management</span>
                </a>
            </li>
            <li class="nav-heading">Leave Management</li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="{{ route('leave_application') }}">
                    <i class="bi bi-grid"></i>
                    <span>Leave Application</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="{{ route('leave_approval') }}">
                    <i class="bi bi-grid"></i>
                    <span>Leave Approval</span>
                </a>
            </li>

            <li class="nav-heading">HR Management</li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="{{ route('employee') }}">
                    <i class="bi bi-grid"></i>
                    <span>Employee</span>
                </a>
            </li>
            <li class="nav-heading">Payroll Management</li>
            <li class="nav-item">
                <a class="nav-link collapsed">
                    <i class="bi bi-grid"></i>
                    <span>
                        Payroll
                        <span class="badge bg-danger text-white">Soon</span>
                    </span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="{{ route('payroll_settings') }}">
                    <i class="bi bi-grid"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed">
                    <i class="bi bi-grid"></i>
                    <span>
                        Adjustments
                        <span class="badge bg-danger text-white">Soon</span>
                    </span>
                </a>
            </li>
            <li class="nav-heading">Settings</li>
            @if(session('access_rights') == 'admin')
                <li class="nav-item">
                    <a class="nav-link collapsed" href="{{ route('calendar') }}">
                        <i class="bi bi-calendar"></i>
                        <span>Calendar</span>
                    </a>
                </li>
                <li class="nav-item d-none">
                    <a class="nav-link collapsed" href="{{ route('hospital_order') }}">
                        <i class="bi bi-calendar"></i>
                        <span>Hospital Orders</span>
                    </a>
                </li>
            @endif
            <li class="nav-item">
                <a class="nav-link collapsed" href="{{ route('timetable') }}">
                    <i class="bi bi-grid"></i>
                    <span>Timetable Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="{{ route('leave_management') }}">
                    <i class="bi bi-grid"></i>
                    <span>Leave Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed">
                    <i class="bi bi-grid"></i>
                    <span>
                        Payroll Settings
                        <span class="badge bg-danger text-white">Soon</span>
                    </span>
                </a>
            </li>
        @endif
        <li class="nav-heading">Mobile DTR</li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="{{ route('download.latest') }}">
                <i class="bi bi-android"></i>
                <span>Download APK</span>
            </a>
        </li>
        <!-- <li class="nav-item">
            <a class="nav-link collapsed" href="{{ route('download.v2') }}">
                <i class="bi bi-android"></i>
                <span>Download APK v2</span>
            </a>
        </li> -->
    </ul>
</aside><!-- End Sidebar-->
