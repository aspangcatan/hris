<aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">
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
        <li class="nav-item">
            <a class="nav-link collapsed" href="{{ route('leave') }}">
                <i class="bi bi-grid"></i>
                <span>File Leave</span>
            </a>
        </li>
        <li class="nav-item d-none">
            <a class="nav-link collapsed" href="{{ route('review_leave') }}">
                <i class="bi bi-grid"></i>
                <span>Review Leave</span>
            </a>
        </li>
        @if(session('access_rights') == "hr")
            <li class="nav-item">
                <a class="nav-link collapsed" href="{{ route('employee') }}">
                    <i class="bi bi-grid"></i>
                    <span>Employee</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="{{ route('leave_management') }}">
                    <i class="bi bi-grid"></i>
                    <span>Leave Management</span>
                </a>
            </li>
        @endif
        @if(session('access_rights') == "admin")
            <li class="nav-item">
                <a class="nav-link collapsed" href="#">
                    <i class="bi bi-grid"></i>
                    <span>DTR <span class="ms-2 badge bg-danger">Soon</span></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="#">
                    <i class="bi bi-grid"></i>
                    <span>Leave <span class="ms-2 badge bg-danger">Soon</span></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" href="#">
                    <i class="bi bi-grid"></i>
                    <span>Payroll <span class="ms-2 badge bg-danger">Soon</span></span>
                </a>
            </li>
        @endif
    </ul>
</aside><!-- End Sidebar-->
