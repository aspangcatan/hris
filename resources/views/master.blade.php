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
    <link href="{{ asset('assets/img/favicon.png') }}" rel="icon">
    <link href="{{ asset('assets/img/apple-touch-icon.png') }}" rel="apple-touch-icon">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="{{  asset('assets/js/tailwind.js') }}"></script>
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

    <script>
        /*
         * Double-click guard for every page. While a POST/PUT/PATCH/DELETE is still on its way,
         * an identical one (same method, URL and body) is not sent again - every caller gets a
         * copy of the first request's response instead. GET requests are untouched. The server
         * has its own checks too; this just stops the duplicate before it leaves the browser.
         */
        (function () {
            if (!window.fetch) return;
            const realFetch = window.fetch.bind(window);
            const inFlight = new Map();

            function bodyKey(body) {
                if (body == null) return '';
                if (typeof body === 'string') return body;
                if (body instanceof URLSearchParams) return body.toString();
                if (body instanceof FormData) {
                    const parts = [];
                    for (const [k, v] of body.entries()) {
                        parts.push(k + '=' + (v instanceof File ? `[file ${v.name} ${v.size} ${v.lastModified}]` : v));
                    }
                    return parts.join('&');
                }
                return null; // Blob, ArrayBuffer, ...: not compared, sent as usual
            }

            window.fetch = function (input, init) {
                const method = String((init && init.method) || 'GET').toUpperCase();
                if (typeof input !== 'string' || method === 'GET' || method === 'HEAD') {
                    return realFetch(input, init);
                }
                const body = bodyKey(init && init.body);
                if (body === null) return realFetch(input, init);

                const key = method + ' ' + input + '\n' + body;
                let pending = inFlight.get(key);
                if (!pending) {
                    pending = realFetch(input, init);
                    inFlight.set(key, pending);
                    const done = () => inFlight.delete(key);
                    pending.then(done, done);
                }
                // Each caller reads its own copy, so a duplicate click never consumes the original.
                return pending.then(r => r.clone());
            };
        })();
    </script>

    <!-- SweetAlert2 JS -->



    <!-- Google Fonts -->
    {{--
    <link href="https://fonts.gstatic.com" rel="preconnect">--}}
    {{-- <link--}} {{--
        href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
        --}} {{-- rel="stylesheet">--}}

        <!-- Vendor CSS Files -->
        <link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
        <link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
        <!-- Template Main CSS File -->
        <link href="{{ asset('css/style.css?v=3') }}" rel="stylesheet">
        <link href="{{ asset('css/toastr.min.css') }}" rel="stylesheet">
        <link href="{{ asset('css/select2.min.css') }}" rel="stylesheet">
        <link href="{{ asset('css/progress.css') }}" rel="stylesheet">
        <link rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css"
            integrity="sha512-mSYUmp1HYZDFaVKK//63EcZq4iFWFjxSL+Z3T/aCt4IO9Cejm03q3NKKYN6pFQzY0SBOr8h+eCIAZHPXcpZaNw=="
            crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        {{-- App theme (same look as E-AMSP / paperless_prescription), loaded last so it wins --}}
        <link href="{{ asset('css/fonts.css') }}" rel="stylesheet">
        <link href="{{ asset('css/app-theme.css') }}?v={{ @filemtime(public_path('css/app-theme.css')) }}" rel="stylesheet">

        <!-- =======================================================
    * Template Name: NiceAdmin - v2.5.0
    * Template URL: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/
    * Author: BootstrapMade.com
    * License: https://bootstrapmade.com/license/
    ======================================================== -->
</head>

<body class="theme-v2">
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

    <main id="main" class="main">
        @php($pageTitle = trim($__env->yieldContent('Title')))
        <div class="m-0 p-0">
            @yield('content')
        </div>
    </main>
    <!-- End #main -->
    <!-- ======= Footer ======= -->
    <footer id="footer" class="footer">
        <div class="copyright">
            &copy; {{ date('Y') }} <strong><span>Cebu South Medical Center</span></strong> &middot; HRIS
        </div>
        <div class="credits">
            Designed by <a href="https://bootstrapmade.com/">BootstrapMade</a>
        </div>
    </footer><!-- End Footer -->

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i
            class="bi bi-arrow-up-short"></i></a>

    <!-- Vendor JS Files -->
    <script src="{{ asset('assets/vendor/apexcharts/apexcharts.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    @include('layouts.row_menu')
    <script src="{{ asset('assets/vendor/chart.js/chart.umd.js') }}"></script>
    <script src="{{ asset('assets/vendor/echarts/echarts.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/quill/quill.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/simple-datatables/simple-datatables.js') }}"></script>
    <script src="{{ asset('assets/vendor/tinymce/tinymce.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/php-email-form/validate.js') }}"></script>
    <!-- Template Main JS File -->
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script src="{{ asset('js/jquery.min.js') }}"></script>
    <script src="{{ asset('js/moment.min.js') }}"></script>
    <script src="{{ asset('js/toastr.min.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"
        integrity="sha512-T/tUfKSV1bihCnd+MxKD0Hm1uBBroVYBOYSk1knyvQ9VyZJpc/ALb4P0r6ubwVPSGB2GvjeoMAJJImBG12TiaQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
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

                if (new_password.length < 4) {
                    is_valid = false;
                    $("#settings_modal .error_list").append("<li>New password must at least 4 characters</li>");
                    $("#new_password").addClass("is-invalid");
                }

                if (new_password != confirm_password) {
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
                    observer.observe(tbody, { childList: true });
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