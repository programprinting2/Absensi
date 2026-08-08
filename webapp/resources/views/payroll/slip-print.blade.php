<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Slip - {{ $period->label }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; font-family: Arial, Helvetica, sans-serif; background: #111827; color: #fff; }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: #111827;
            border-bottom: 1px solid #374151;
            font-size: 13px;
        }
        .toolbar button, .toolbar a {
            appearance: none;
            border: 0;
            border-radius: 6px;
            padding: 7px 12px;
            font: inherit;
            cursor: pointer;
            text-decoration: none;
            color: #111;
            background: #fff;
        }
        .toolbar .primary { background: #f7340d; color: #fff; font-weight: 600; }
        .toolbar .muted { background: #374151; color: #e5e7eb; }
        .toolbar .hint { opacity: 0.8; margin-left: auto; font-size: 12px; max-width: 420px; text-align: right; }
        .stage { height: calc(100% - 52px); background: #4b5563; }
        .stage iframe { width: 100%; height: 100%; border: 0; background: #fff; }
        .status { padding: 24px; text-align: center; color: #e5e7eb; }
        .status.error { color: #fecaca; }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" id="btn-print">Print</button>
        <a href="{{ $pdfDownloadUrl }}" class="muted">Unduh PDF</a>
        <button type="button" class="muted" onclick="window.close()">Tutup</button>
        <span class="hint">
            {{ \App\Support\PaySlipPaper::options()[$paper] ?? $paper }} · {{ $count }} slip
            · Print dari PDF (tanpa tanggal/URL browser)
        </span>
    </div>

    <div class="stage">
        <div class="status" id="status">Menyiapkan PDF…</div>
        <iframe id="pdf-frame" title="Slip PDF" hidden></iframe>
    </div>

    <script>
        (function () {
            const inlineUrl = @json($pdfInlineUrl);
            const frame = document.getElementById('pdf-frame');
            const status = document.getElementById('status');
            const btnPrint = document.getElementById('btn-print');
            let blobUrl = null;
            let ready = false;

            function setError(msg) {
                status.hidden = false;
                status.classList.add('error');
                status.textContent = msg;
                frame.hidden = true;
            }

            function doPrint() {
                if (!ready) return;
                try {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                } catch (e) {
                    window.open(blobUrl, '_blank');
                }
            }

            btnPrint.addEventListener('click', doPrint);

            fetch(inlineUrl, { credentials: 'same-origin' })
                .then(function (res) {
                    if (!res.ok) throw new Error('Gagal memuat PDF (' + res.status + ')');
                    return res.blob();
                })
                .then(function (blob) {
                    if (blobUrl) URL.revokeObjectURL(blobUrl);
                    blobUrl = URL.createObjectURL(blob);
                    frame.src = blobUrl;
                    frame.hidden = false;
                    status.hidden = true;
                    ready = true;

                    frame.onload = function () {
                        @if ($autoPrint ?? false)
                            setTimeout(doPrint, 400);
                        @endif
                    };
                })
                .catch(function (err) {
                    setError(err.message + ' — coba Unduh PDF lalu print dari viewer.');
                });

            window.addEventListener('beforeunload', function () {
                if (blobUrl) URL.revokeObjectURL(blobUrl);
            });
        })();
    </script>
</body>
</html>
