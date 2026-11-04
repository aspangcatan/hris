<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Module {{ $moduleNo }}: {{ $module['title'] }} | CSMC in Sync</title>
    <link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
    <style>
        * { box-sizing:border-box; }
        html, body { margin:0; height:100%; }
        body { font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; background:#1e293b; color:#0f172a;
               user-select:none; -webkit-user-select:none; display:flex; flex-direction:column; }

        .rd-bar { flex:0 0 auto; display:flex; align-items:center; gap:1rem; padding:.7rem 1.25rem; color:#fff;
                  background:linear-gradient(135deg,#0f766e 0%,#0e7490 55%,#155e75 100%); box-shadow:0 4px 18px rgba(0,0,0,.25); z-index:2; }
        .rd-icon { width:40px; height:40px; flex:0 0 40px; border-radius:12px; display:flex; align-items:center; justify-content:center;
                   font-size:1.2rem; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.25); }
        .rd-title { min-width:0; flex:1; }
        .rd-title small { display:block; font-size:.68rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:rgba(255,255,255,.75); }
        .rd-title h1 { margin:0; font-size:1.05rem; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rd-tools { display:flex; align-items:center; gap:.4rem; }
        .rd-chip { font-size:.85rem; font-variant-numeric:tabular-nums; padding:.4rem .75rem; border-radius:999px; background:rgba(255,255,255,.14); white-space:nowrap; }
        .rd-btn { border:0; cursor:pointer; width:36px; height:36px; border-radius:10px; color:#fff; font-size:1rem;
                  background:rgba(255,255,255,.14); display:inline-flex; align-items:center; justify-content:center; transition:background .15s; }
        .rd-btn:hover { background:rgba(255,255,255,.26); }
        .rd-btn:disabled { opacity:.4; cursor:default; }
        .rd-close { width:auto; padding:0 .9rem; gap:.4rem; font-weight:600; font-size:.9rem; }
        .rd-sep { width:1px; height:24px; background:rgba(255,255,255,.25); margin:0 .25rem; }

        .rd-view { flex:1; overflow:auto; padding:1.5rem 1rem 3rem; }
        .rd-view canvas { display:block; margin:0 auto 1.25rem; background:#fff; border-radius:4px;
                          box-shadow:0 10px 30px rgba(0,0,0,.35); pointer-events:none; }
        .rd-status { color:#cbd5e1; text-align:center; padding:4rem 1rem; }
        .rd-status i { display:block; font-size:2rem; margin-bottom:.75rem; }
        .rd-spin { animation:rdSpin 1s linear infinite; display:inline-block; }
        @keyframes rdSpin { to { transform:rotate(360deg); } }

        .rd-foot { flex:0 0 auto; text-align:center; font-size:.75rem; color:#94a3b8; padding:.45rem 1rem; background:#0f172a; }

        @media (max-width:640px) {
            .rd-bar { flex-wrap:wrap; gap:.6rem; padding:.6rem .9rem; }
            .rd-icon { display:none; }
            .rd-tools { width:100%; justify-content:space-between; }
            .rd-sep { display:none; }
            .rd-view { padding:1rem .5rem 2rem; }
        }
        @media print { body { display:none !important; } }
    </style>
</head>
<body>
    <header class="rd-bar">
        <div class="rd-icon"><i class="bi bi-book-half"></i></div>
        <div class="rd-title">
            <small>CSMC in Sync &middot; Module {{ $moduleNo }}</small>
            <h1>{{ $module['title'] }}</h1>
        </div>
        <div class="rd-tools">
            <span class="rd-chip" id="rd_page">&ndash;</span>
            <span class="rd-sep"></span>
            <button class="rd-btn" id="rd_zoom_out" title="Zoom out"><i class="bi bi-zoom-out"></i></button>
            <span class="rd-chip" id="rd_zoom">100%</span>
            <button class="rd-btn" id="rd_zoom_in" title="Zoom in"><i class="bi bi-zoom-in"></i></button>
            <span class="rd-sep"></span>
            <button class="rd-btn rd-close" id="rd_close" title="Close this tab"><i class="bi bi-x-lg"></i> Close</button>
        </div>
    </header>

    <main class="rd-view" id="rd_view">
        <div class="rd-status"><i class="bi bi-arrow-repeat rd-spin"></i>Loading module...</div>
    </main>

    <footer class="rd-foot">
        <i class="bi bi-shield-lock"></i> For CSMC internal use only &middot; View only &mdash; not for reproduction or distribution
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const PDF_URL = "{{ route('reorientation.module_pdf', $moduleNo) }}";
        const ZOOMS = [0.5, 0.75, 1, 1.25, 1.5, 2];
        const view = document.getElementById('rd_view');
        let zoomIndex = 2;
        let canvases = [];

        // Modules are view only: no right-click, drag, save or print
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('dragstart', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && ['p', 's'].includes(e.key.toLowerCase())) e.preventDefault();
        });

        document.getElementById('rd_close').addEventListener('click', () => window.close());
        document.getElementById('rd_zoom_in').addEventListener('click', () => setZoom(zoomIndex + 1));
        document.getElementById('rd_zoom_out').addEventListener('click', () => setZoom(zoomIndex - 1));
        window.addEventListener('resize', () => setZoom(zoomIndex));

        // 100% = fit the page to the reading width
        function baseWidth() {
            return Math.min(view.clientWidth - 32, 900);
        }

        function setZoom(index) {
            zoomIndex = Math.max(0, Math.min(ZOOMS.length - 1, index));
            const width = baseWidth() * ZOOMS[zoomIndex];
            canvases.forEach(c => c.style.width = width + 'px');
            document.getElementById('rd_zoom').textContent = Math.round(ZOOMS[zoomIndex] * 100) + '%';
            document.getElementById('rd_zoom_out').disabled = zoomIndex === 0;
            document.getElementById('rd_zoom_in').disabled = zoomIndex === ZOOMS.length - 1;
        }

        async function render() {
            try {
                const pdf = await pdfjsLib.getDocument({url: PDF_URL, withCredentials: true}).promise;
                const pageLabel = document.getElementById('rd_page');
                pageLabel.textContent = `1 / ${pdf.numPages}`;
                view.innerHTML = '';

                // Track the page in view for the page counter
                const observer = new IntersectionObserver(entries => {
                    entries.forEach(en => {
                        if (en.isIntersecting) pageLabel.textContent = `${en.target.dataset.page} / ${pdf.numPages}`;
                    });
                }, {root: view, threshold: 0.5});

                // Render sharp enough for 200% zoom
                const scale = Math.min(3, (window.devicePixelRatio || 1) * 2);
                for (let i = 1; i <= pdf.numPages; i++) {
                    const page = await pdf.getPage(i);
                    const viewport = page.getViewport({scale: scale});
                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    canvas.dataset.page = i;
                    view.appendChild(canvas);
                    canvases.push(canvas);
                    setZoom(zoomIndex);
                    observer.observe(canvas);
                    await page.render({canvasContext: canvas.getContext('2d'), viewport: viewport}).promise;
                }
            } catch (e) {
                view.innerHTML = '<div class="rd-status"><i class="bi bi-exclamation-circle"></i>Could not load the module. Please close this tab and try again.</div>';
            }
        }

        render();
    </script>
</body>
</html>
