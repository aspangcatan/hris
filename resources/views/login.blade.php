<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Human Resource Information System">
    <title>HRIS | Sign In</title>

    <link rel="icon" href="{{ asset('assets/img/favicon.png') }}" sizes="16x16" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            min-height: 100vh;
            background:
                radial-gradient(circle at 12% 20%, rgba(14, 165, 233, 0.24), transparent 28%),
                radial-gradient(circle at 86% 16%, rgba(20, 184, 166, 0.23), transparent 30%),
                linear-gradient(150deg, #eaf2fb 0%, #f8fafc 45%, #edf4ff 100%);
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.76);
            border: 1px solid rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.16);
        }

        .brand-grad {
            background: linear-gradient(130deg, #0f766e, #0ea5a4, #0284c7);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
    </style>
</head>

<body>
<main class="mx-auto grid min-h-screen max-w-7xl items-center px-4 py-8 lg:grid-cols-2 lg:gap-8 lg:px-8">
    <section class="glass-panel hidden rounded-[28px] p-8 lg:block">
        <div class="mb-8 inline-flex items-center gap-3 rounded-full bg-white/70 px-4 py-2 text-sm font-semibold text-slate-700">
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
            CSMC Human Resource Information System
        </div>
        <h1 class="text-4xl font-extrabold leading-tight text-slate-900 xl:text-5xl">
            Workforce operations,
            <span class="brand-grad">centralized and secure.</span>
        </h1>
        <p class="mt-5 max-w-xl text-base leading-7 text-slate-600">
            Manage employee records, leave workflows, scheduling, and HR compliance from one modern internal platform.
        </p>
        <div class="mt-8 grid gap-3 text-sm text-slate-700">
            <div class="rounded-2xl bg-white/80 p-4 shadow-sm">
                <p class="font-semibold text-slate-900">Employee Profiles</p>
                <p class="mt-1 text-slate-600">Unified records for personal, service, and statutory data.</p>
            </div>
            <div class="rounded-2xl bg-white/80 p-4 shadow-sm">
                <p class="font-semibold text-slate-900">Leave and DTR</p>
                <p class="mt-1 text-slate-600">Track attendance, leave balances, and approval workflows.</p>
            </div>
            <div class="rounded-2xl bg-white/80 p-4 shadow-sm">
                <p class="font-semibold text-slate-900">Governance Ready</p>
                <p class="mt-1 text-slate-600">Support SALN, reports, and audit-ready HR processes.</p>
            </div>
        </div>
    </section>

    <section class="mx-auto w-full max-w-md rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_24px_70px_rgba(15,23,42,.18)] backdrop-blur md:p-8">
        <div class="mb-6 text-center">
            <img src="{{ asset('assets/img/logo.png') }}" alt="CSMC Logo" class="mx-auto h-16 w-16 rounded-2xl bg-slate-100 p-2 shadow-sm">
            <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900">HRIS Portal</h2>
            <p class="mt-1 text-sm text-slate-500">Sign in with your employee credentials</p>
        </div>

        @if(Session::has('message'))
            <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ Session::get('message') }}
            </div>
        @endif

        <form id="loginForm" class="space-y-4" method="post" action="{{ route('authenticate') }}">
            {{ csrf_field() }}
            <div>
                <label for="inputUsername" class="mb-1 block text-sm font-semibold text-slate-700">Username</label>
                <input type="text" name="username" id="inputUsername"
                       class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-100"
                       placeholder="Enter username" required autofocus>
            </div>
            <div>
                <label for="inputPassword" class="mb-1 block text-sm font-semibold text-slate-700">Password</label>
                <input type="password" name="password" id="inputPassword"
                       class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-100"
                       placeholder="Enter password" required>
            </div>
            <button class="mt-1 w-full rounded-xl bg-gradient-to-r from-teal-700 via-teal-600 to-sky-600 px-4 py-3 text-sm font-bold uppercase tracking-wider text-white shadow-[0_12px_28px_rgba(15,118,110,.35)] transition hover:brightness-105"
                    type="submit">
                Sign In
            </button>
        </form>

        <p class="mt-5 text-center text-xs text-slate-500">
            Authorized users only. All activities are monitored.
        </p>
    </section>
</main>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if(Session::has('message'))
        Swal.fire({
            icon: 'error',
            title: 'Connection Timeout',
            text: @json(Session::get('message')),
            confirmButtonText: 'OK'
        });
        @endif
    });
</script>
</body>
</html>
