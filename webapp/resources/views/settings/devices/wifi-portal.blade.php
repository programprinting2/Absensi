<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WiFi Manager — {{ $device->name }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .card { background: #fff; border-radius: 0.5rem; box-shadow: 0 1px 3px rgb(0 0 0 / 0.1); max-width: 28rem; width: 100%; padding: 1.5rem; }
        h1 { font-size: 1.125rem; font-weight: 600; margin: 0; color: #111827; }
        .muted { color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem; }
        .ok { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 0.375rem; padding: 0.75rem 1rem; font-size: 0.875rem; }
        ol { font-size: 0.875rem; color: #374151; padding-left: 1.25rem; line-height: 1.6; }
        .btn { display: block; width: 100%; text-align: center; padding: 0.75rem 1rem; background: #4f46e5; color: #fff; font-weight: 600; border-radius: 0.375rem; text-decoration: none; margin-top: 0.5rem; }
        .btn:hover { background: #4338ca; }
        .hint { font-size: 0.75rem; color: #9ca3af; margin-top: 1rem; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Setup WiFi Device</h1>
        <p class="muted">{{ $device->name }} ({{ $device->device_code }})</p>

        <p class="ok" style="margin: 1rem 0;">Perintah setup WiFi sudah dikirim ke device. Tunggu ±10 detik agar access point device aktif.</p>

        <ol>
            <li>Di HP/laptop ini, buka pengaturan WiFi.</li>
            <li>Sambungkan ke <strong>{{ $apName }}</strong></li>
            <li>Password: <strong>{{ $apPassword }}</strong></li>
            <li>Setelah terhubung, klik tombol di bawah.</li>
        </ol>

        <a href="{{ $portalUrl }}" class="btn">Buka WiFi Manager ({{ $portalUrl }})</a>

        <p class="hint">
            Portal hanya bisa dibuka setelah terhubung ke WiFi <strong>{{ $apName }}</strong>.
            Jika halaman tidak muncul, pastikan device online dan firmware terbaru sudah di-flash.
        </p>
    </div>
</body>
</html>
