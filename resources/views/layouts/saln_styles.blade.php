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

        /* Modal & Form Refinement */
        #saln_form_modal .modal-content {
            border: none;
            border-radius: 0;
            background: #fcfdfe;
        }

        #saln_form_modal .modal-header,
        #saln_form_modal .saln-premium-header {
            background: linear-gradient(135deg, var(--saln-primary) 0%, var(--saln-primary-dark) 100%);
            padding: 1.25rem 2rem;
            border: none;
        }

        .saln-premium-header-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,.18);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #fff;
            flex-shrink: 0;
        }

        #saln_form_modal .modal-title {
            font-weight: 800;
            letter-spacing: -0.5px;
            text-transform: none;
        }

        .saln-form-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 0;
        }

        .saln-section-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 10px 40px rgba(0,0,0,0.02);
            padding: 2.5rem;
            margin-bottom: 2rem;
        }

        .saln-tab-nav {
            border: none !important;
            background: #f1f3f5;
            padding: 6px;
            border-radius: 14px;
            display: inline-flex;
            gap: 4px;
            margin-bottom: 2rem;
        }

        .saln-tab-nav .nav-link {
            border: none !important;
            border-radius: 10px !important;
            padding: 10px 24px;
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--saln-text-muted);
            transition: all 0.3s ease;
            background: transparent !important;
        }

        .saln-tab-nav .nav-link.active {
            background: #fff !important;
            color: var(--saln-primary) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .saln-sub-tab-nav {
            border-bottom: 2px solid #f1f3f5 !important;
            margin-bottom: 2.5rem;
            gap: 20px;
        }

        .saln-sub-tab-nav .nav-link {
            border: none !important;
            padding: 12px 4px;
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--saln-text-muted);
            position: relative;
            background: transparent !important;
        }

        .saln-sub-tab-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--saln-primary);
            transition: width 0.3s ease;
        }

        .saln-sub-tab-nav .nav-link.active {
            color: var(--saln-primary) !important;
        }

        .saln-sub-tab-nav .nav-link.active::after {
            width: 100%;
        }

        .form-label-premium {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--saln-text-muted);
            margin-bottom: 8px;
        }

        .form-control-premium {
            border: 1.5px solid #edf2f7;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 500;
            color: var(--saln-text-main);
            transition: all 0.2s ease;
        }

        .form-control-premium:focus {
            border-color: var(--saln-primary);
            box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.12);
            background: #fff;
        }

        .form-control-premium[readonly] {
            background: #f8fafc;
            border-color: #f1f5f9;
            color: #94a3b8;
        }

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

        .confirmation-footer {
            background: #fff;
            border-top: 1px solid #edf2f7;
            padding: 2rem 3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.03);
            position: sticky;
            bottom: 0;
            z-index: 1050;
        }

        .footer-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .footer-info-item {
            display: flex;
            flex-direction: column;
        }

        .footer-info-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--saln-text-muted);
            text-transform: uppercase;
        }

        .footer-info-value {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--saln-primary);
        }

        .btn-finalize {
            background: var(--saln-primary);
            color: #fff;
            border: none;
            padding: 14px 36px;
            border-radius: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 8px 20px rgba(15, 118, 110, 0.25);
        }

        .btn-finalize:hover {
            background: var(--saln-primary-dark);
            transform: scale(1.02);
            box-shadow: 0 12px 28px rgba(15, 118, 110, 0.35);
            color: #fff;
        }

        .btn-finalize i {
            font-size: 1.2rem;
        }

        .saln-card-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--saln-text-main);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .saln-card-title i {
            width: 40px;
            height: 40px;
            background: var(--saln-primary-light);
            color: var(--saln-primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .custom-option {
            padding: 8px 16px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .custom-option:hover {
            border-color: var(--saln-primary);
            background: var(--saln-primary-light);
        }

        .fw-800 { font-weight: 800; }
        .fw-600 { font-weight: 600; }
    </style>
