# Absensi Online

Sistem absensi karyawan berbasis ESP32 (sidik jari ZW111 + keypad matrix 4x4 +
LCD TFT ST7735) dengan dashboard Laravel dan PostgreSQL.

## Struktur Proyek

- `firmware/` — firmware ESP32 (PlatformIO)
- `webapp/` — dashboard manajemen Laravel
- `supabase/` — skema database & migration (source of truth)
- `docs/` — dokumentasi arsitektur & catatan keamanan

## Setup Webapp (dua mesin, satu repo)

File `.env` **tidak** di-commit — setiap PC punya `.env` sendiri. Setelah `git pull`:

```bash
cd webapp
cp .env.example .env          # hanya pertama kali / mesin baru
# edit .env → APP_URL, DB_HOST, DB_PASSWORD
composer install
npm install
php artisan key:generate
php artisan migrate
npm run dev:watch             # development (Laravel + Vite)
```

| | PC utama `.249` (production) | PC dev `.100` (remote fixing) |
|--|------------------------------|-------------------------------|
| `APP_URL` | `http://192.168.100.249:8008` | `http://192.168.100.100:8008` |
| `DB_HOST` | `127.0.0.1` | `192.168.100.249` |
| `DB_TIMEZONE` | `UTC` | `UTC` |

Vite dev server otomatis mengikuti hostname `APP_URL` — tidak perlu hardcode IP di kode.

Production tanpa Vite dev: `npm run build` lalu `php artisan serve --host=0.0.0.0 --port=8008`.

## ESP32

Portal WiFi Manager → **Server URL** = `APP_URL` mesin yang menjalankan Laravel
(contoh production: `http://192.168.100.249:8008`, API Key kosong).

Default first-boot ada di `firmware/src/config.h` (production `.249`).

Lihat `docs/security-notes.md` untuk detail strategi keamanan akses device.
