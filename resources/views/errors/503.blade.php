{{-- resources/views/errors/503.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>An error occurred</title>
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
            position: relative;
        }
        .meta-right {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 0.75rem;
            color: #9ca3af;
            font-family: monospace;
        }
        .badge {
            background-color: #fee2e2;
            color: #991b1b;
            font-size: 0.75rem;
            font-family: monospace;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            display: inline-block;
            font-weight: bold;
        }
        .error-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-top: 1rem;
            margin-bottom: 0;
            font-family: monospace;
            color: #111827;
        }
        .split-container {
            display: flex;
            background: white;
            border-radius: 4px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            margin: 0 auto;
            min-height: 520px;
            border: 1px solid #e5e7eb;
        }
        .sidebar {
            width: 30%;
            border-right: 1px solid #e5e7eb;
            font-size: 0.85rem;
            background-color: #f9fafb;
        }
        .sidebar-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-family: monospace;
            color: #4b5563;
            cursor: default;
        }
        .sidebar-item.active {
            background-color: #ef4444;
            color: white;
        }
        .sidebar-item.vendor {
            color: #9ca3af;
            background-color: #f3f4f6;
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
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
            line-height: 1.6;
        }
        .line-num {
            color: #9ca3af;
            width: 30px;
            text-align: left;
            vertical-align: top;
            user-select: none;
        }
        .line-content {
            padding-left: 1rem;
            white-space: pre;
            color: #374151;
        }
        .line-error {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
        }
        .line-error .line-num {
            padding-left: 4px;
        }
        .keyword { color: #d946ef; }
        .string { color: #059669; }
        .comment { color: #9ca3af; }
        .variable { color: #2563eb; }
    </style>
</head>
<body>

    <!-- Top Exception Box -->
    <div class="error-card">
        <div class="meta-right">PHP 8.2.10 · Laravel 10.24.0</div>
        <div class="badge">ErrorException</div>
        <div class="error-title">
            Undefined variable $activeUserConfig in processing pipeline cluster.
        </div>
    </div>

    <!-- Main Code Simulation Box -->
    <div class="split-container">
        <!-- Left Sidebar (Simulated Stack Trace) -->
        <div class="sidebar">
            <div class="sidebar-item vendor">? Expand vendor frames</div>
            <div class="sidebar-item vendor">5 vendor frames ?</div>
            
            <div class="sidebar-item active">
                app/Http/Controllers/DashboardController.php:14
                <br><span style="font-size: 0.75rem; opacity: 0.85;">index</span>
            </div>
            
            <div class="sidebar-item">
                app/Http/Middleware/VerifyCsrfToken.php:41
                <br><span style="font-size: 0.75rem; color: #9ca3af;">handle</span>
            </div>
            
            <div class="sidebar-item vendor">22 vendor frames ?</div>
            
            <div class="sidebar-item">
                public/index.php:52
                <br><span style="font-size: 0.75rem; color: #9ca3af;">require</span>
            </div>
        </div>

        <!-- Right Code View (Simulated Code File) -->
        <div class="code-section">
            <div class="file-path">app/Http/Controllers/DashboardController.php : line 14</div>
            
            <table class="code-table">
                <tr>
                    <td class="line-num">11</td>
                    <td class="line-content">    <span class="keyword">public function</span> <span class="string">index</span>() {</td>
                </tr>
                <tr>
                    <td class="line-num">12</td>
                    <td class="line-content">        <span class="variable">$metrics</span> = Metrics::getRuntimeData();</td>
                </tr>
                <tr>
                    <td class="line-num">13</td>
                    <td class="line-content">        </td>
                </tr>
                <tr class="line-error">
                    <td class="line-num">14</td>
                    <td class="line-content">        <span class="keyword">if</span> (<span class="variable">$activeUserConfig</span>-&gt;hasActiveSession()) {</td>
                </tr>
                <tr>
                    <td class="line-num">15</td>
                    <td class="line-content">            <span class="comment">// Process stream handshake sequence</span></td>
                </tr>
                <tr>
                    <td class="line-num">16</td>
                    <td class="line-content">            <span class="variable">$this</span>-&gt;dispatchQueueEngine(<span class="variable">$metrics</span>);</td>
                </tr>
                <tr>
                    <td class="line-num">17</td>
                    <td class="line-content">        }</td>
                </tr>
            </table>
        </div>
    </div>

</body>
</html>