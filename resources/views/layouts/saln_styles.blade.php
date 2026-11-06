    <style>
        :root {
            --saln-primary: #0f766e;
            --saln-primary-dark: #115e59;
            --saln-primary-light: #ccfbf1;
            --saln-text-main: #0f172a;
            --saln-text-muted: #64748b;
            --saln-bg-soft: #f8fafc;
        }

        .saln-header {
            background: linear-gradient(135deg, var(--saln-primary) 0%, var(--saln-primary-dark) 100%);
            border-radius: 16px;
            padding: 32px;
            color: #fff;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(15, 118, 110, 0.15);
            position: relative;
            overflow: hidden;
        }

        .saln-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .saln-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .saln-header p {
            margin: 8px 0 0;
            opacity: 0.85;
            font-size: 1rem;
            font-weight: 400;
        }

        .saln-filter-bar {
            background: #fff;
            border-radius: 12px;
            padding: 20px 28px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .saln-record-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.05);
            padding: 24px;
            margin-bottom: 20px;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative;
            cursor: default;
        }

        .saln-record-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.08);
            border-color: var(--saln-primary);
        }

        .saln-year-badge {
            background: var(--saln-primary-light);
            color: var(--saln-primary);
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            font-size: 1.4rem;
            font-weight: 800;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.12);
        }

        .saln-info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 700;
            color: var(--saln-text-muted);
            margin-bottom: 4px;
        }

        .saln-info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--saln-text-main);
        }

        .saln-networth-value {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--saln-primary);
        }

        .filing-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .filing-badge.joint { background: #e3f2fd; color: #1976d2; }
        .filing-badge.separate { background: #fff3e0; color: #ef6c00; }
        .filing-badge.na { background: #f5f5f5; color: #616161; }

        .btn-action {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-edit { background: #e8f5e9; color: #2e7d32; }
        .btn-edit:hover { background: #2e7d32; color: #fff; }
        
        .btn-delete { background: #ffebee; color: #c62828; }
        .btn-delete:hover { background: #c62828; color: #fff; }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: var(--saln-bg-soft);
            border-radius: 20px;
            color: var(--saln-text-muted);
            border: 2px dashed #dee2e6;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .underline {
            border: none;
            border-bottom: 2px solid #dee2e6;
            outline: none;
            background: transparent;
            font-weight: 600;
            padding: 2px 4px;
            transition: border-color 0.2s ease;
        }
        
        .underline:focus { border-color: var(--saln-primary); }

        /* Premium Loader Overlay */
        .saln-loader-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loader-content {
            background: #fff;
            padding: 40px 60px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            text-align: center;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .premium-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--saln-primary);
            border-radius: 50%;
            animation: premium-spin 0.8s cubic-bezier(0.5, 0.1, 0.4, 0.9) infinite;
            margin: 0 auto 24px;
        }

        @keyframes premium-spin {
            to { transform: rotate(360deg); }
        }

        /* Shared by settings/cutoff_summaries */
        .saln-table-premium thead th {
            background: #f8f9fa;
            border: none;
            color: var(--saln-text-muted);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 16px;
        }

        .saln-table-premium tbody td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f3f5;
            font-weight: 500;
        }

        .fw-800 { font-weight: 800; }
        .fw-600 { font-weight: 600; }

        /* ===================== SALN form modal (#saln_form_modal) =====================
           Built on the theme-v2 tokens (app-theme.css): v2 modal header, cards on the soft
           background, navy primary. Class prefix: sf- */
        .sf-modal .modal-dialog { max-width: min(1180px, calc(100vw - 2rem)); }
        .sf-modal .sf-body { display: flex; flex-direction: column; gap: 1rem; padding: 1.25rem; background: var(--background); }

        .sf-guide {
            display: flex; gap: 0.75rem; align-items: flex-start;
            padding: 0.75rem 1rem; border: 1px solid var(--primary-fixed); border-radius: 0.75rem;
            background: var(--primary-soft); color: var(--on-surface-variant); font-size: 0.875rem;
        }
        .sf-guide > .bi { color: var(--primary); font-size: 1.1rem; line-height: 1.3; }
        .sf-guide > div { display: flex; flex-wrap: wrap; column-gap: 1rem; row-gap: 0.125rem; }
        .sf-guide strong { color: var(--on-surface); }

        .sf-card { padding: 1.125rem 1.25rem; background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); }
        .sf-card-head { display: flex; align-items: flex-start; gap: 0.875rem; margin-bottom: 1rem; }
        .sf-card-icon {
            display: flex; flex-shrink: 0; align-items: center; justify-content: center;
            width: 2.25rem; height: 2.25rem; border-radius: 0.625rem;
            background: var(--primary-fixed); color: var(--primary); font-size: 1rem;
        }
        .sf-card-text { min-width: 0; flex: 1; }
        .sf-card-text h6 { margin: 0; font: 700 1rem/1.4 var(--font-head); color: var(--on-surface); }
        .sf-card-text p { margin: 0.125rem 0 0; font-size: 0.8125rem; color: var(--outline); }
        .sf-add { flex-shrink: 0; white-space: nowrap; }

        .sf-label { display: block; margin-bottom: 0.375rem; font-size: 0.8125rem; font-weight: 600; color: var(--on-surface-variant); }
        .sf-hint { margin-top: 0.375rem; font-size: 0.75rem; color: var(--outline); min-height: 1rem; }

        .sf-date { display: grid; grid-template-columns: 1.6fr 0.8fr 1fr; gap: 0.5rem; }

        /* Segmented radio choice */
        .sf-seg { display: flex; padding: 0.25rem; gap: 0.25rem; border: 1px solid var(--outline-variant); border-radius: 0.75rem; background: var(--surface-low); }
        .sf-seg > label {
            flex: 1; margin: 0; padding: 0.45rem 0.5rem; border-radius: 0.5rem; text-align: center; cursor: pointer;
            font-size: 0.8125rem; font-weight: 600; color: var(--on-surface-variant) !important; white-space: nowrap;
            transition: background-color .15s ease, color .15s ease;
        }
        .sf-seg > label:hover { background: var(--surface); color: var(--primary) !important; }
        .sf-seg > .btn-check:checked + label { background: var(--primary); color: #fff !important; box-shadow: 0 1px 2px rgba(19, 27, 46, .2); }
        .sf-seg > .btn-check:focus-visible + label { outline: 2px solid var(--primary); outline-offset: 2px; }

        /* Declarant / Spouse switch */
        .sf-who { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .sf-who-btn {
            display: grid; grid-template-columns: auto 1fr; column-gap: 0.75rem; align-items: center; text-align: left;
            padding: 0.75rem 1rem; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface);
            color: var(--on-surface-variant); transition: border-color .15s ease, box-shadow .15s ease;
        }
        .sf-who-btn i { grid-row: span 2; display: flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem;
            border-radius: 50%; background: var(--surface-low); color: var(--outline); font-size: 1rem; }
        .sf-who-btn span { font: 700 0.9375rem/1.3 var(--font-head); color: var(--on-surface); }
        .sf-who-btn small { font-size: 0.75rem; color: var(--outline); }
        .sf-who-btn:hover { border-color: var(--outline-variant); }
        .sf-who-btn.active { border-color: var(--primary); box-shadow: inset 0 0 0 1px var(--primary); }
        .sf-who-btn.active i { background: var(--primary); color: #fff; }

        /* Section tabs: one scrollable row */
        .sf-tabs { flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; margin-bottom: 1rem; scrollbar-width: thin; }
        .sf-tabs .nav-link { white-space: nowrap; display: inline-flex; align-items: center; gap: 0.375rem; }
        .sf-count:empty { display: none; }
        .sf-count {
            min-width: 1.25rem; padding: 0 0.375rem; border-radius: 999px; background: var(--surface-low);
            font-size: 0.6875rem; font-weight: 700; line-height: 1.25rem; text-align: center; color: var(--outline);
        }
        .sf-tabs .nav-link.active .sf-count { background: var(--primary); color: #fff; }

        /* "I have none" declaration */
        .sf-none {
            display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.875rem; padding: 0.625rem 0.875rem;
            border: 1px dashed var(--outline-variant); border-radius: 0.75rem; background: var(--surface-low);
            font-size: 0.875rem; font-weight: 600; color: var(--on-surface) !important; cursor: pointer;
        }
        .sf-none .form-check-input { margin: 0; flex-shrink: 0; }
        .sf-none:has(input:checked) { border-style: solid; border-color: var(--primary-fixed); background: var(--primary-soft); }

        /* Lists */
        .sf-table-shell { border: 1px solid var(--line); border-radius: 0.75rem; background: var(--surface); }
        .sf-table-shell thead th small { display: block; margin-top: 0.125rem; font-size: 0.625rem; font-weight: 500; letter-spacing: 0; text-transform: none; }
        .sf-table-shell tbody tr:last-child > * { border-bottom: 0; }
        .sf-table-shell .sf-col-actions { width: 7rem; }
        .sf-empty { display: none; padding: 1.5rem 1rem; text-align: center; color: var(--outline); font-size: 0.875rem; }
        .sf-empty .bi { display: block; margin-bottom: 0.25rem; font-size: 1.5rem; opacity: .6; }
        .sf-empty small { font-size: 0.8125rem; }
        .sf-table-shell:has(tbody:empty) .sf-empty { display: block; }
        .sf-table-shell:has(tbody:empty) thead { display: none; }

        .sf-next-row { display: flex; justify-content: flex-end; margin-top: 1rem; }
        .sf-done { font-size: 0.8125rem; color: var(--secondary); }

        .sf-footer { justify-content: space-between; }
        .sf-status { display: flex; flex-direction: column; min-width: 0; }
        .sf-status-label { font-size: 0.6875rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--outline); }
        .sf-status-value { font-size: 0.875rem; font-weight: 600; color: var(--primary); }

        @media (max-width: 767.98px) {
            .sf-who { grid-template-columns: 1fr 1fr; }
            .sf-who-btn small { display: none; }
            .sf-card-head { flex-wrap: wrap; }
            .sf-add { width: 100%; }
            .sf-guide > div { flex-direction: column; }
        }
        @media (max-width: 575.98px) {
            .sf-modal .sf-body { padding: 0.875rem; }
            .sf-card { padding: 0.875rem; }
            .sf-status { display: none; }
            .sf-seg > label { font-size: 0.75rem; padding: 0.45rem 0.25rem; }
        }

        /* Print SALN dialog: rows per table */
        .saln-print-rows { padding: 0.75rem; border: 1px solid var(--line); border-radius: 0.75rem; background: var(--surface-low); }
        .saln-print-rows-title { font-size: 0.8125rem; font-weight: 700; color: var(--on-surface); }
        .saln-print-rows-hint { font-size: 0.75rem; color: var(--outline); }
        .saln-print-rows .form-control { background: var(--surface); }

        /* Inline editing inputs */
        .saln-inline-input {
            font-size: .82rem;
            padding: .25rem .45rem;
            border: 1px solid #d8e1ee;
            border-radius: 6px;
            width: 100%;
            min-width: 55px;
            background: #fff;
            color: #1e293b;
            transition: border-color .15s;
        }
        .saln-inline-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-fixed);
        }
        .saln-inline-input.is-invalid { border-color: #dc3545; }
        .saln-inline-row td { vertical-align: middle; padding: .4rem .5rem; background: var(--primary-soft); }
    </style>
