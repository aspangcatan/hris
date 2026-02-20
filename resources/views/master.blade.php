<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>HRIS</title>
    <meta content="" name="description">
    <meta content="" name="keywords">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                    },
                },
            },
        };
    </script>
    <style>
        :root {
            --app-bg: #eef3f9;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --line: #dbe3ee;
            --brand-600: #0f766e;
            --brand-500: #0ea5a4;
            --brand-100: #ccfbf1;
            --shadow-soft: 0 14px 36px rgba(2, 32, 71, 0.08);
            --shadow-card: 0 18px 44px rgba(15, 23, 42, 0.08);
        }

        body {
            background:
                radial-gradient(circle at 10% -5%, rgba(14, 165, 233, 0.12), transparent 26%),
                radial-gradient(circle at 95% 0%, rgba(45, 212, 191, 0.1), transparent 22%),
                var(--app-bg) !important;
            color: var(--text-primary);
        }

        #header.header {
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.88);
            border-bottom: 1px solid rgba(219, 227, 238, 0.9);
            box-shadow: 0 8px 24px rgba(2, 32, 71, 0.06);
        }

        #header .logo span {
            letter-spacing: .08em;
            font-weight: 800;
            color: #0f172a;
        }

        #header .toggle-sidebar-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            color: #0f172a;
            background: #f1f5f9;
        }

        #header .nav-profile img {
            border: 2px solid #dbeafe;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.14);
        }

        #header .dropdown-menu {
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        #sidebar .nav-heading,
        #sidebar .nav-link {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
        }

        #sidebar .nav-heading {
            letter-spacing: .04em;
            font-weight: 700;
        }

        #sidebar .nav-link {
            letter-spacing: .01em;
            font-weight: 500;
        }

        #main.main {
            padding-top: 50px !important;
            background: transparent;
        }

        #main.main.main-profile {
            padding-top: 0 !important;
        }

        .pagetitle h1,
        .card-title,
        h1, h2, h3, h4, h5, h6 {
            color: var(--text-primary);
        }

        .pagetitle h1 {
            font-weight: 800;
            letter-spacing: .01em;
            font-size: 1.55rem;
        }

        .card {
            border: 1px solid rgba(219, 227, 238, 0.9);
            border-radius: 20px;
            background: var(--surface);
            box-shadow: var(--shadow-card);
        }

        .card .card-body {
            color: var(--text-secondary);
        }

        .table {
            --bs-table-bg: transparent;
            --bs-table-striped-bg: rgba(248, 250, 252, 0.75);
            --bs-table-hover-bg: rgba(240, 253, 250, 0.8);
            border-color: var(--line);
        }

        .table thead th {
            border-bottom: 1px solid var(--line);
            background: #f8fafc;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-size: .73rem;
            font-weight: 700;
        }

        .table td, .table th {
            border-color: var(--line);
            vertical-align: middle;
        }

        .form-control,
        .form-select,
        .selectize-input {
            border: 1px solid #cbd5e1 !important;
            border-radius: 12px !important;
            min-height: 42px;
            box-shadow: none !important;
        }

        .form-control:focus,
        .form-select:focus,
        .selectize-input.focus {
            border-color: var(--brand-500) !important;
            box-shadow: 0 0 0 4px rgba(14, 165, 164, 0.14) !important;
        }

        .form-floating > label {
            color: #64748b;
        }

        .btn {
            border-radius: 12px;
            font-weight: 600;
        }

        .btn-success,
        .btn-primary {
            border: 0;
            background: linear-gradient(120deg, var(--brand-600), var(--brand-500));
            box-shadow: 0 10px 22px rgba(15, 118, 110, 0.25);
        }

        .btn-success:hover,
        .btn-primary:hover {
            filter: brightness(1.04);
        }

        .btn-secondary {
            border: 0;
            background: #334155;
        }

        .modal-action-row {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 1.5rem;
        }

        .btn-modal-close {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #334155;
            border-radius: 12px;
            padding: 0.55rem 1rem;
            font-weight: 600;
            transition: all 0.18s ease;
        }

        .btn-modal-close:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #334155;
        }

        .btn-modal-save {
            border: 0;
            color: #fff;
            border-radius: 12px;
            padding: 0.55rem 1rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            background: linear-gradient(120deg, #0f766e, #0ea5a4);
            box-shadow: 0 10px 24px rgba(15, 118, 110, 0.24);
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
        }

        .btn-modal-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 30px rgba(15, 118, 110, 0.28);
            filter: brightness(1.03);
            color: #fff;
        }

        /* Unify Bootstrap success semantics with the profile theme */
        .text-success {
            color: var(--brand-600) !important;
        }

        .bg-success {
            background-color: var(--brand-600) !important;
        }

        .border-success,
        .border-success-subtle {
            border-color: rgba(15, 118, 110, 0.35) !important;
        }

        .bg-success-subtle {
            background-color: rgba(15, 118, 110, 0.12) !important;
        }

        .btn-outline-success {
            color: var(--brand-600) !important;
            border-color: rgba(15, 118, 110, 0.45) !important;
        }

        .btn-outline-success:hover {
            color: #fff !important;
            background-color: var(--brand-600) !important;
            border-color: var(--brand-600) !important;
        }

        .spinner-border.text-success {
            color: var(--brand-600) !important;
        }

        .badge {
            border-radius: 9999px;
            padding: .35em .65em;
        }

        .dropdown-menu {
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: var(--shadow-soft);
        }

        .nav-tabs,
        .nav-tabs-bordered {
            border-bottom-color: var(--line);
        }

        .nav-tabs .nav-link,
        .nav-tabs-bordered .nav-link {
            border: 0;
            color: #475569;
            font-weight: 600;
            border-radius: 10px;
            margin-right: .25rem;
        }

        .nav-tabs .nav-link.active,
        .nav-tabs-bordered .nav-link.active {
            color: #0f766e;
            background: #ecfeff;
            box-shadow: inset 0 -2px 0 #0f766e;
        }

        .pagination .page-link {
            border-radius: 10px;
            border-color: var(--line);
            color: #334155;
            margin: 0 2px;
        }

        .pagination .active > .page-link {
            background: var(--brand-500);
            border-color: var(--brand-500);
        }

        .empty-record-row td {
            color: #64748b;
            font-style: italic;
            background: #f8fafc;
        }

        .fullscreen-modal {
            backdrop-filter: blur(5px);
            background: rgba(2, 6, 23, 0.58) !important;
        }

        .premium-modal .modal-content {
            border: 0;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 30px 70px rgba(15, 23, 42, 0.28);
        }

        .premium-modal .modal-header {
            border: 0;
            padding: 0;
            background: linear-gradient(135deg, #0f766e, #0e7490);
        }

        .premium-modal .modal-hero {
            width: 100%;
            padding: 1.1rem 1rem;
            text-align: center;
        }

        .premium-modal .modal-body {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            padding: 1.3rem;
        }

        .premium-modal .modal-title-text {
            color: #0f172a;
            font-size: 1.2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: .9rem;
        }

        .premium-modal .premium-input,
        .premium-modal .premium-select {
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: .65rem .85rem;
            background: #fff;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .premium-modal .premium-input:focus,
        .premium-modal .premium-select:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.14);
            outline: 0;
        }

        #footer.footer {
            background: transparent;
            color: #64748b;
        }
    </style>

    <!-- SweetAlert2 JS -->



    <!-- Google Fonts -->
{{--    <link href="https://fonts.gstatic.com" rel="preconnect">--}}
{{--    <link--}}
{{--        href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"--}}
{{--        rel="stylesheet">--}}

<!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <!-- Template Main CSS File -->
    <link href="assets/css/style.css?v={{ date('ymdhis') }}" rel="stylesheet">
    <link href="css/toastr.min.css?v={{ date('ymdhis') }}" rel="stylesheet">
    <link href="css/selectize.default.min.css?v={{ date('ymdhis') }}" rel="stylesheet">
    <link href="css/progress.css?v={{ date('ymdhis') }}" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css" integrity="sha512-mSYUmp1HYZDFaVKK//63EcZq4iFWFjxSL+Z3T/aCt4IO9Cejm03q3NKKYN6pFQzY0SBOr8h+eCIAZHPXcpZaNw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- =======================================================
    * Template Name: NiceAdmin - v2.5.0
    * Template URL: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/
    * Author: BootstrapMade.com
    * License: https://bootstrapmade.com/license/
    ======================================================== -->
</head>

<body style="font-family: 'Plus Jakarta Sans', system-ui, sans-serif;">
<div class="fullscreen-modal d-none" id="spinner">
    <div class="spinner-border text-light" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <div class="text-white fs-5 mt-1">Loading, please wait ...</div>
</div>
<!-- ======= Header ======= -->
@include('layouts.navbar')
<!-- ======= Sidebar ======= -->
@include('layouts.sidebar')
@include('modals.settings')

<main id="main" class="main {{ request()->routeIs('profile') ? 'main-profile' : '' }}">
    @php($pageTitle = trim($__env->yieldContent('Title')))
    @if($pageTitle !== '')
        <div class="pagetitle">
            <h1>{{ $pageTitle }}</h1>
        </div>
    @endif
    <div class="m-0 p-0">
        @yield('content')
    </div>
</main>
<!-- End #main -->
<!-- ======= Footer ======= -->
<footer id="footer" class="footer">
    <div class="copyright">
        &copy; Copyright <strong><span>NiceAdmin</span></strong>. All Rights Reserved
    </div>
    <div class="credits">
        <!-- All the links in the footer should remain intact. -->
        <!-- You can delete the links only if you purchased the pro version. -->
        <!-- Licensing information: https://bootstrapmade.com/license/ -->
        <!-- Purchase the pro version with working PHP/AJAX contact form: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/ -->
        Designed by <a href="https://bootstrapmade.com/">BootstrapMade</a>
    </div>
</footer><!-- End Footer -->

<a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i
        class="bi bi-arrow-up-short"></i></a>

<!-- Vendor JS Files -->
<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/chart.js/chart.umd.js"></script>
<script src="assets/vendor/echarts/echarts.min.js"></script>
<script src="assets/vendor/quill/quill.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/tinymce/tinymce.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>
<!-- Template Main JS File -->
<script src="assets/js/main.js"></script>
<script src="js/jquery.min.js"></script>
<script src="js/moment.min.js"></script>
<script src="js/toastr.min.js"></script>
<script src="js/selectize.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js" integrity="sha512-T/tUfKSV1bihCnd+MxKD0Hm1uBBroVYBOYSk1knyvQ9VyZJpc/ALb4P0r6ubwVPSGB2GvjeoMAJJImBG12TiaQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    const settings_modal = new bootstrap.Modal(document.getElementById('settings_modal'));
    toastr.options = {
        "positionClass": "toast-bottom-right",
    }
    $(document).ready(function () {
        $("#change_password").click(function (e) {
            e.preventDefault();
            settings_modal.toggle();
        });

        $("#btn_change_password").click(function () {
            let is_valid = true;
            $("#settings_modal .is-invalid").removeClass("is-invalid");
            $("#settings_modal .error_container").addClass("d-none");
            $("#settings_modal .error_list").empty();

            const old_password = $("#old_password").val().trim();
            const new_password = $("#new_password").val().trim();
            const confirm_password = $("#confirm_password").val().trim();

            if (old_password == "") {
                is_valid = false;
                $("#settings_modal .error_list").append("<li>Old password is required</li>");
                $("#old_password").addClass("is-invalid");
            }

            if (new_password == "") {
                is_valid = false;
                $("#settings_modal .error_list").append("<li>New password is required</li>");
                $("#old_password").addClass("is-invalid");
            }

            if (confirm_password == "") {
                is_valid = false;
                $("#settings_modal .error_list").append("<li>Confirm password is required</li>");
                $("#old_password").addClass("is-invalid");
            }

            if(new_password.length < 4){
                is_valid = false;
                $("#settings_modal .error_list").append("<li>New password must at least 4 characters</li>");
                $("#new_password").addClass("is-invalid");
            }

            if(new_password != confirm_password){
                is_valid = false;
                $("#settings_modal .error_list").append("<li>Password mismatch</li>");
                $("#new_password").addClass("is-invalid");
            }

            if (!is_valid) {
                $("#settings_modal .error_container").removeClass("d-none");
                return;
            }

            const params = {
                old_password: old_password,
                new_password: new_password,
                confirm_password: confirm_password
            }

            fetch('{{route('changePassword')}}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Something went wrong")
                        return;
                    }

                    settings_modal.toggle();
                    toastr.success(data.message, "Information");
                    $("#old_password").val("");
                    $("#new_password").val("");
                    $("#confirm_password").val("");
                }).catch(err => {
                alert(err);
            });
        });
    });

    // Global list-table empty row handler:
    // Any table tbody with an id will show a simple "No records found" row when empty.
    (function setupGlobalEmptyTableRows() {
        function getColspanForTbody(tbody) {
            const table = tbody.closest("table");
            if (!table) return 1;

            const headerRow = table.querySelector("thead tr:last-child");
            if (!headerRow) return 1;

            let colspan = 0;
            headerRow.querySelectorAll("th").forEach(function (th) {
                const span = parseInt(th.getAttribute("colspan") || "1", 10);
                colspan += isNaN(span) ? 1 : span;
            });

            return colspan > 0 ? colspan : 1;
        }

        function ensureEmptyRow(tbody) {
            if (!tbody || !tbody.id) return;

            const rows = Array.from(tbody.querySelectorAll(":scope > tr"));
            const hasDataRows = rows.some(function (row) {
                return !row.classList.contains("empty-record-row");
            });

            if (hasDataRows) {
                rows.forEach(function (row) {
                    if (row.classList.contains("empty-record-row")) {
                        row.remove();
                    }
                });
                return;
            }

            if (!tbody.querySelector(":scope > tr.empty-record-row")) {
                const tr = document.createElement("tr");
                tr.className = "empty-record-row";
                const td = document.createElement("td");
                td.className = "text-center py-3";
                td.setAttribute("colspan", String(getColspanForTbody(tbody)));
                td.textContent = "No records found";
                tr.appendChild(td);
                tbody.appendChild(tr);
            }
        }

        function bindObservers() {
            const tbodies = document.querySelectorAll("table tbody[id]");
            tbodies.forEach(function (tbody) {
                if (tbody.dataset.emptyObserverBound === "1") {
                    ensureEmptyRow(tbody);
                    return;
                }

                const observer = new MutationObserver(function () {
                    ensureEmptyRow(tbody);
                });
                observer.observe(tbody, {childList: true});
                tbody.dataset.emptyObserverBound = "1";
                ensureEmptyRow(tbody);
            });
        }

        document.addEventListener("DOMContentLoaded", bindObservers);
        window.addEventListener("load", bindObservers);
        setTimeout(bindObservers, 300);
    })();
</script>
@yield('js')
</body>

</html>
