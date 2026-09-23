{{-- resources/views/errors/503.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 | Service Unavailable</title>
    <style>
        body {
            background-color: #f3f4f6;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 2rem;
            color: #1f2937;
        }
        .error-card {
            background: white;
            border-radius: 4px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            max-width: 1200px;
            margin: 0 auto 1.5rem auto;
            padding: 1.5rem;
        }
        .badge {
            background-color: #f3f4f6;
            color: #4b5563;
            font-size: 0.75rem;
            font-family: monospace;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            display: inline-block;
        }
        .error-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-top: 1rem;
            margin-bottom: 0;
            font-family: monospace;
        }
        .split-container {
            display: flex;
            background: white;
            border-radius: 4px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            margin: 0 auto;
            min-height: 400px;
            border: 1px solid #e5e7eb;
        }
        .sidebar {
            width: 30%;
            border-right: 1px solid #e5e7eb;
            font-size: 0.85rem;
        }
        .sidebar-item {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            font-family: monospace;
        }
        .sidebar-item.active {
            background-color: #ef4444;
            color: white;
        }
        .code-section {
            width: 70%;
            padding: 1.5rem;
            background-color: #ffffff;
            font-family: monospace;
            position: relative;
        }
        .file-path {
            color: #6b7280;
            font-size: 0.85rem;
            text-align: right;
            margin-bottom: 1rem;
        }
        .code-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .line-num {
            color: #9ca3af;
            width: 30px;
            text-align: left;
            user-select: none;
        }
        .line-content {
            padding-left: 1rem;
            white-space: pre;
        }
        .line-error {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
        }
        .line-error .line-num {
            padding-left: 4px;
        }
    </style>
</head>
<body>

    <!-- Top Exception Box -->
    <div class="error-card">
        <div class="badge">MaintenanceModeException</div>
        <div class="error-title">
            The application is temporarily down for scheduled updates.
        </div>
    </div>

    <!-- Main Code Simulation Box -->
    <div class="split-container">
        <!-- Left Sidebar -->
        <div class="sidebar">
            <div class="sidebar-item active">
                resources/views/index.blade.php
                <br><span style="font-size: 0.75rem; opacity: 0.8;">down</span>
            </div>
            <div class="sidebar-item" style="color: #9ca3af;">
                1 vendor frame hidden
            </div>
        </div>

        <!-- Right Code View -->
        <div class="code-section">
            <div class="file-path">resources/views/index.blade.php</div>
            
            <table class="code-table">
                <tr>
                    <td class="line-num">1</td>
                    <td class="line-content" style="color: #6b7280;">&lt;!-- PHP Vendor --&gt;</td>
                </tr>
                <tr>
                    <td class="line-num">2</td>
                    <td class="line-content">&lt;x-maintenance-layout&gt;</td>
                </tr>
                <tr>
                    <td class="line-num">3</td>
                    <td class="line-content">    <span style="color: #059669;">@include('errors.partials.status')</span></td>
                </tr>
                <tr class="line-error">
                    <td class="line-num">4</td>
                    <td class="line-content">    <strong>{{ $exception->getMessage() ?: 'Database migration and cache clearing in progress.' }}</strong></td>
                </tr>
                <tr>
                    <td class="line-num">5</td>
                    <td class="line-content">&lt;/x-maintenance-layout&gt;</td>
                </tr>
            </table>
        </div>
    </div>

</body>
</html>