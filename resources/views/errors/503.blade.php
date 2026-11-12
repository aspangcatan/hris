{{-- resources/views/errors/503.blade.php
     Shown while the app is in maintenance mode (php artisan down) or on any 503.
     Self-contained on purpose: no layout, session, auth or database calls, so it
     renders even when those are unavailable. --}}
@php
    $retryAfter = isset($exception) && method_exists($exception, 'getHeaders')
        ? ($exception->getHeaders()['Retry-After'] ?? null)
        : null;
    $refreshSeconds = is_numeric($retryAfter) ? max(30, min((int) $retryAfter, 600)) : 60;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Under Maintenance · HRIS</title>
    <link rel="icon" href="{{ asset('assets/img/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00236f;
            --primary-container: #1e3a8a;
            --primary-fixed: #dce1ff;
            --primary-soft: #eef0ff;
            --secondary: #006a61;
            --secondary-container: #86f2e4;
            --on-secondary-container: #00504a;
            --warning-container: #ffdcbe;
            --on-warning-container: #7a4100;
            --background: #f5f6fc;
            --surface: #ffffff;
            --surface-low: #f2f3ff;
            --on-surface: #131b2e;
            --on-surface-variant: #444651;
            --outline: #757682;
            --line: #e6e8f2;
            --radius: 0.875rem;
            --font-body: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            --font-head: 'Plus Jakarta Sans', var(--font-body);
        }

        * { box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            margin: 0;
            font-family: var(--font-body);
            font-size: 0.9375rem;
            line-height: 1.6;
            color: var(--on-surface);
            background:
                radial-gradient(circle at 12% 8%, rgba(30, 58, 138, 0.10), transparent 42%),
                radial-gradient(circle at 88% 92%, rgba(0, 106, 97, 0.10), transparent 40%),
                var(--background);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1.25rem 1.5rem;
        }
        .topbar img { height: 40px; width: auto; }
        .brand-name {
            font-family: var(--font-head);
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--primary);
            line-height: 1.1;
        }
        .brand-sub { display: block; font-size: 0.75rem; color: var(--outline); }

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 1rem 2rem;
        }

        .card {
            width: 100%;
            max-width: 560px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 20px;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
            padding: 2.5rem 2.25rem 2rem;
            text-align: center;
        }

        .illustration {
            width: 96px;
            height: 96px;
            margin: 0 auto 1.25rem;
            border-radius: 24px;
            background: var(--primary-soft);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .illustration svg { width: 52px; height: 52px; }
        .gear { transform-origin: 50% 50%; animation: spin 8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--on-warning-container);
            background: var(--warning-container);
            border-radius: 999px;
            padding: 0.3rem 0.8rem;
        }
        .chip-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: currentColor;
            animation: pulse 1.6s ease-in-out infinite;
        }
        @keyframes pulse { 50% { opacity: 0.3; } }

        h1 {
            font-family: var(--font-head);
            font-weight: 800;
            font-size: 1.75rem;
            line-height: 1.25;
            color: var(--primary);
            margin: 1rem 0 0.5rem;
        }

        .lead {
            color: var(--on-surface-variant);
            margin: 0 auto 1.5rem;
            max-width: 430px;
        }

        .info {
            text-align: left;
            background: var(--surface-low);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 1rem 1.15rem;
            margin-bottom: 1.5rem;
        }
        .info-title {
            font-family: var(--font-head);
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--on-surface);
            margin-bottom: 0.4rem;
        }
        .info ul { margin: 0; padding-left: 1.15rem; color: var(--on-surface-variant); font-size: 0.875rem; }
        .info li + li { margin-top: 0.25rem; }

        .actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.65rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: var(--font-body);
            font-weight: 600;
            font-size: 0.9rem;
            color: #fff;
            background: var(--primary);
            border: 0;
            border-radius: 10px;
            padding: 0.7rem 1.5rem;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.15s ease;
        }
        .btn:hover { background: var(--primary-container); }
        .btn:active { transform: translateY(1px); }
        .btn:focus-visible { outline: 3px solid var(--primary-fixed); outline-offset: 2px; }
        .btn svg { width: 16px; height: 16px; }

        .countdown { font-size: 0.8rem; color: var(--outline); }
        .countdown strong { color: var(--on-surface-variant); font-variant-numeric: tabular-nums; }

        footer {
            text-align: center;
            font-size: 0.75rem;
            color: var(--outline);
            padding: 0 1rem 1.5rem;
        }

        @media (max-width: 480px) {
            .card { padding: 2rem 1.25rem 1.5rem; border-radius: 16px; }
            h1 { font-size: 1.45rem; }
            .topbar { padding: 1rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            .gear, .chip-dot { animation: none; }
        }
    </style>
</head>
<body>

<header class="topbar">
    <img src="{{ asset('assets/img/logo.png') }}" alt="Cebu South Medical Center logo">
    <div>
        <div class="brand-name">HRIS</div>
        <span class="brand-sub">Human Resources</span>
    </div>
</header>

<main>
    <div class="card" role="main">
        <div class="illustration" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="#00236f" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <g class="gear">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </g>
            </svg>
        </div>

        <span class="chip"><span class="chip-dot"></span> Scheduled maintenance</span>

        <h1>We'll be back shortly</h1>
        <p class="lead">
            HRIS is temporarily unavailable while we apply updates and improvements.
            This usually only takes a few minutes.
        </p>

        <div class="info">
            <div class="info-title">While you wait</div>
            <ul>
                <li>Your records, leave applications and DTR entries are safe. Nothing is lost.</li>
                <li>If you were in the middle of filing something, please submit it again once the system is back.</li>            </ul>
        </div>

        <div class="actions">
            <button type="button" class="btn" onclick="window.location.reload()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/>
                </svg>
                Try again
            </button>
            <div class="countdown" aria-live="polite">
                Checking again automatically in <strong id="countdown">{{ $refreshSeconds }}</strong>s
            </div>
        </div>
    </div>
</main>

<footer>
    &copy; {{ date('Y') }} Cebu South Medical Center · Human Resource Information System
</footer>

<script>
    (function () {
        var remaining = {{ $refreshSeconds }};
        var el = document.getElementById('countdown');
        var timer = setInterval(function () {
            remaining -= 1;
            if (el) el.textContent = Math.max(remaining, 0);
            if (remaining <= 0) {
                clearInterval(timer);
                window.location.reload();
            }
        }, 1000);
    })();
</script>
</body>
</html>
